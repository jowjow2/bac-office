<?php

namespace App\Http\Controllers;

use App\Mail\LoginVerificationCodeMail;
use App\Mail\PasswordResetCodeMail;
use App\Mail\BidderIncompleteRequirementsMail;
use App\Models\BidderDocument;
use App\Models\Project;
use App\Models\User;
use App\Support\BidderRegistrationRequirements;
use App\Support\LoginAudit;
use App\Support\SystemNotification;
use App\Support\Uploads;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    protected const PASSWORD_RESET_CODE_TTL_SECONDS = 180;

    /** Pending sign-in awaiting its emailed code: the code's hash, expiry, send time and tries. */
    protected const LOGIN_VERIFICATION_SESSION = 'login_verification';

    protected function authResponse(
        Request $request,
        bool $ok,
        string $message,
        string $tab = 'login',
        int $status = 200,
        ?string $redirect = null,
        array $errors = [],
        array $extra = []
    ) {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(array_merge([
                'ok' => $ok,
                'message' => $message,
                'tab' => $tab,
                'redirect' => $redirect,
                'errors' => $errors,
            ], $extra), $status);
        }

        if ($ok && $redirect) {
            return redirect()->to($redirect)->with('success', $message);
        }

        $flashKey = $ok ? 'success' : 'error';

        return back()
            ->withInput($request->except(['password']))
            ->with($flashKey, $message)
            ->with('auth_tab', $tab);
    }

    public function showLoginPage(Request $request)
    {
        $scannedProjectId = $request->query('qr_project');
        $project = $scannedProjectId && ctype_digit((string) $scannedProjectId) && Schema::hasTable('projects')
            ? Project::query()->visibleToPublic()->find((int) $scannedProjectId)
            : null;

        // "Login to Participate" / project QR: a bidder goes to that project; anyone
        // else (guest, or signed in as BAC/End-user) sees the login modal first.
        if ($project) {
            $user = Auth::user();

            if ($user?->role === 'bidder') {
                return redirect()->to($this->participationUrl($project));
            }

            $request->session()->put('participation_project_id', $project->id);

            return redirect()
                ->route('home')
                ->with('auth_tab', 'login')
                ->with('scanned_project_title', $project->title)
                ->with('scanned_project_reference', $project->reference_no)
                ->with('scanned_project_category', $project->category ? (string) Str::of($project->category)->replace('_', ' ')->title() : 'Uncategorized')
                ->with('scanned_project_signed_in_as', $user ? $this->roleLabel($user) : null);
        }

        if (Auth::check()) {
            return redirect()->to($this->redirectForUser(Auth::user()));
        }

        return redirect()
            ->route('home')
            ->with('auth_tab', (string) $request->query('auth_tab', 'login'));
    }

    /** Where a bidder goes to take part in a scanned project: its bid page while it is open. */
    protected function participationUrl(Project $project): string
    {
        return $project->status === 'open'
            ? route('bidder.opportunities.show', $project)
            : route('public.procurement.show', $project);
    }

    /** After login: a bidder who started from "Login to Participate" returns to that project. */
    /** The sign-in modal greets the user by name while it opens their portal. */
    protected function welcomePayload(User $user): array
    {
        $name = $user->role === 'bidder' ? ($user->company ?: $user->name) : $user->name;

        return ['welcome' => ['name' => (string) $name]];
    }

    /** Bidders get a welcome card on their dashboard once per sign-in (BidderController::index). */
    protected function queueBidderWelcome(Request $request, User $user): void
    {
        if ($user->role === 'bidder') {
            $request->session()->put('bidder_welcome', true);
        }
    }

    protected function redirectAfterLogin(Request $request, User $user): string
    {
        $projectId = $request->session()->pull('participation_project_id');

        if ($user->role === 'bidder' && $projectId && Schema::hasTable('projects')) {
            $project = Project::query()->visibleToPublic()->find($projectId);

            if ($project) {
                return $this->participationUrl($project);
            }
        }

        return $this->redirectForUser($user);
    }

    protected function roleLabel(User $user): string
    {
        return match ($user->role) {
            'admin' => 'BAC Admin',
            'staff' => 'BAC Staff',
            'end_user' => 'End-user office',
            default => ucfirst((string) $user->role),
        };
    }

    /**
     * Where this browser's registration documents go when it uploads them
     * straight to Blob storage: a random folder kept in its session, so one
     * visitor can neither use nor guess another's uploads.
     */
    public static function registrationUploadFolder(Request $request): string
    {
        $folder = $request->session()->get('registration_upload_folder');
        if (! is_string($folder) || ! preg_match('#^registration-uploads/[A-Za-z0-9]{32}$#', $folder)) {
            $folder = 'registration-uploads/'.Str::random(32);
            $request->session()->put('registration_upload_folder', $folder);
        }

        return $folder;
    }

    /**
     * Issues the short-lived token @vercel/blob "upload" asks for, so each
     * registration document goes from the browser straight to private Blob
     * storage: a serverless request takes at most 4.5 MB, which a full set of
     * eligibility documents exceeds (413).
     */
    public function registrationUploadToken(Request $request)
    {
        abort_unless(\App\Support\VercelBlob::enabled(), 404);
        if ($request->input('type') !== 'blob.generate-client-token') {
            return response()->json(['error' => 'Unsupported upload request.'], 422);
        }

        $pathname = (string) $request->input('payload.pathname');
        $folder = self::registrationUploadFolder($request);
        if (! preg_match('#^'.preg_quote($folder, '#').'/[A-Za-z0-9._-]{1,150}\.(pdf|jpg|jpeg|png)$#i', $pathname)) {
            return response()->json(['error' => 'Use PDF, JPG or PNG files.'], 422);
        }
        $maxKb = (int) config('bac-office.registration.max_document_size_kb', 20480);

        return response()->json([
            'type' => 'blob.generate-client-token',
            'clientToken' => \App\Support\VercelBlob::clientToken($pathname, $maxKb * 1024, ['application/pdf', 'image/jpeg', 'image/png']),
        ]);
    }

    public function register(Request $request)
    {
        // Documents uploaded straight to Blob storage arrive as references: fetch them so
        // they get exactly the same checks and storage as posted files, then discard them.
        try {
            [$uploaded, $urls, $temps] = \App\Support\VercelBlob::pullUploads(
                (array) $request->input('uploaded_registration_documents', []),
                (array) $request->input('uploaded_registration_document_names', []),
                self::registrationUploadFolder($request),
                'registration_documents'
            );
        } catch (ValidationException $exception) {
            return $this->authResponse($request, false, collect($exception->errors())->flatten()->first(), 'register', 422, null, $exception->errors());
        }

        try {
            if ($uploaded !== []) {
                $request->files->set('registration_documents', $uploaded + (array) $request->files->get('registration_documents', []));
            }

            return $this->processRegistration($request);
        } finally {
            \App\Support\VercelBlob::discardUploads($urls, $temps);
        }
    }

    private function processRegistration(Request $request)
    {
        if (! $this->authTablesAvailable()) {
            return $this->authResponse(
                $request,
                false,
                'Registration is temporarily unavailable because the application database is not connected yet.',
                'register',
                503
            );
        }

        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
            'role' => strtolower(trim((string) $request->input('role', 'bidder'))),
            'name' => trim((string) $request->name),
            'company' => trim((string) $request->company),
            'contact_person' => trim((string) $request->contact_person),
            'contact_number' => trim((string) $request->contact_number),
            'business_address' => trim((string) $request->business_address),
            'office' => trim((string) $request->office),
            'registration_no' => trim((string) $request->registration_no),
        ]);

        $missingRegistrationRequirements = [];

        if ($request->input('role') === 'bidder') {
            foreach (BidderRegistrationRequirements::documents() as $key => $document) {
                if (($document['required'] ?? false) && ! $request->hasFile("registration_documents.{$key}")) {
                    $missingRegistrationRequirements[] = $document['label'];
                }
            }
        }

        $documentRules = [];
        $documentMessages = [];

        foreach (BidderRegistrationRequirements::documents() as $key => $document) {
            $documentRules["registration_documents.{$key}"] = [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                ($document['required'] ?? false) ? 'required' : 'nullable',
                'file',
                'mimes:' . BidderRegistrationRequirements::FILE_EXTENSIONS,
                'max:20480',
            ];

            $documentMessages["registration_documents.{$key}.required"] = $document['label'] . ' must be uploaded.';
            $documentMessages["registration_documents.{$key}.file"] = $document['label'] . ' must be a valid file.';
            $documentMessages["registration_documents.{$key}.mimes"] = $document['label'] . ' must be a PDF, JPG, JPEG, or PNG file.';
            $documentMessages["registration_documents.{$key}.max"] = $document['label'] . ' must not be larger than 20 MB.';
        }

        $validator = Validator::make($request->all(), array_merge([
            'role' => ['required', Rule::in(['bidder', 'staff'])],
            'name' => [
                Rule::excludeIf($request->input('role') !== 'staff'),
                'required',
                'string',
                'max:255',
            ],
            'company' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'required',
                'string',
                'max:255',
            ],
            'contact_person' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'required',
                'string',
                'max:255',
            ],
            'contact_number' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'required',
                'string',
                'regex:/^[0-9+\-\s()]{7,15}$/',
            ],
            'business_address' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'required',
                'string',
                'max:1000',
            ],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8)->mixedCase()->numbers()->symbols()],
            'office' => [
                Rule::excludeIf($request->input('role') !== 'staff'),
                'required',
                'string',
                'max:255',
                Rule::in(User::staffOfficeOptions()),
            ],
            'registration_no' => [
                Rule::excludeIf($request->input('role') !== 'bidder'),
                'required',
                'string',
                'max:255',
            ],
        ], $documentRules), array_merge([
            'role.required' => 'Account type is required.',
            'role.in' => 'Please select a valid account type.',
            'name.required' => 'Name is required for staff registration.',
            'company.required' => 'Company is required.',
            'contact_person.required' => 'Contact person is required.',
            'contact_number.required' => 'Contact number is required.',
            'contact_number.regex' => 'Enter a valid contact number (7-15 digits, e.g. 09171234567).',
            'business_address.required' => 'Business address is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.mixed' => 'Password must include uppercase and lowercase letters.',
            'password.numbers' => 'Password must include at least one number.',
            'password.symbols' => 'Password must include at least one special character.',
            'office.required' => 'Office is required for staff registration.',
            'office.in' => 'Please select a valid staff office.',
            'registration_no.required' => 'Registration number is required.',
        ], $documentMessages));

        if ($validator->fails()) {
            $registrationRequirementIssues = $missingRegistrationRequirements;

            if ($request->input('role') === 'bidder') {
                foreach (BidderRegistrationRequirements::documents() as $key => $document) {
                    if ($validator->errors()->has("registration_documents.{$key}")) {
                        $registrationRequirementIssues[] = $document['label'];
                    }
                }
            }

            $this->sendIncompleteBidderRequirementsEmail(
                $request,
                array_values(array_unique($registrationRequirementIssues))
            );

            return $this->authResponse(
                $request,
                false,
                $validator->errors()->first(),
                'register',
                422,
                null,
                $validator->errors()->toArray()
            );
        }

        if (User::where('email', $request->email)->exists()) {
            return $this->authResponse($request, false, 'An account with this email already exists. Please sign in instead.', 'register', 422, null, [
                'email' => ['An account with this email already exists.'],
            ]);
        }

        // One bidder account per business: the registration number may not be reused.
        if ($request->input('role') === 'bidder' && User::registrationNumberTaken($request->registration_no)) {
            $message = 'A bidder with this business registration number is already registered. Sign in to that account, or contact the BAC Secretariat if it is not yours.';

            return $this->authResponse($request, false, $message, 'register', 422, null, [
                'registration_no' => [$message],
            ]);
        }

        if ($request->input('role') === 'staff') {
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'staff',
                'status' => 'pending',
                'office' => $request->office,
            ]);

            SystemNotification::createForRole(
                'admin',
                'New staff registration',
                $request->name . ' selected ' . $request->office . ' and is pending activation.',
                'staff_registration',
                [
                    'email' => $request->email,
                    'office' => $request->office,
                ]
            );
        } else {
            if (! Schema::hasTable('bidders') || ! Schema::hasTable('bidder_documents')) {
                return $this->authResponse(
                    $request,
                    false,
                    'Bidder registration is temporarily unavailable because the bidder review tables are not ready yet.',
                    'register',
                    503
                );
            }

            $storedPaths = [];

            try {
                DB::beginTransaction();

                $user = User::create([
                    'name' => $request->contact_person,
                    'company' => $request->company,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'registration_no' => $request->registration_no,
                    'role' => 'bidder',
                    'status' => 'pending',
                ]);

                $bidder = $user->bidderProfile()->create([
                    'company_name' => $request->company,
                    'contact_person' => $request->contact_person,
                    'contact_number' => $request->contact_number,
                    'business_address' => $request->business_address,
                    'approval_status' => 'pending',
                ]);

                $primaryDocumentPath = null;

                // Documents go to storage in parallel; all are confirmed before the commit.
                \App\Support\VercelBlob::batchWrites(function () use ($request, $user, &$storedPaths, &$primaryDocumentPath) {
                    foreach (BidderRegistrationRequirements::documents() as $key => $document) {
                        $file = $request->file("registration_documents.{$key}");

                        if (! $file) {
                            continue;
                        }

                        $filename = 'registration_' . $user->id . '_' . $key . '_' . Str::random(12) . '.' . strtolower($file->getClientOriginalExtension());
                        $storedPath = Uploads::store($file, 'bidder-registration-documents/' . $user->id, $filename);
                        $storedPaths[] = $storedPath;
                        $primaryDocumentPath ??= $storedPath;

                        BidderDocument::create([
                            'user_id' => $user->id,
                            'document_type' => $document['document_type'],
                            'original_name' => $file->getClientOriginalName(),
                            'file_path' => $storedPath,
                            'status' => 'uploaded',
                            'uploaded_at' => now(),
                        ]);
                    }
                });

                if ($primaryDocumentPath) {
                    $bidder->forceFill(['document_path' => $primaryDocumentPath])->save();
                }

                SystemNotification::createForRole(
                    'admin',
                    'New bidder registration',
                    $request->company . ' registration is pending approval.',
                    'bidder_registration',
                    ['email' => $request->email, 'user_id' => $user->id]
                );

                DB::commit();
            } catch (Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                foreach ($storedPaths as $path) {
                    Uploads::delete($path);
                }

                report($e);

                return $this->authResponse(
                    $request,
                    false,
                    'Registration could not be completed. Please check the uploaded files and try again.',
                    'register',
                    500
                );
            }
        }

        return $this->authResponse(
            $request,
            true,
            'Registered successfully! Your account is pending admin approval.',
            'register'
        );
    }

    public function login(Request $request)
    {
        if (! $this->authTablesAvailable()) {
            return $this->authResponse(
                $request,
                false,
                'Login is temporarily unavailable because the application database is not connected yet.',
                'login',
                503
            );
        }

        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required'],
            'remember' => ['nullable', 'boolean'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse($request, false, $validator->errors()->first(), 'login', 422, null, $validator->errors()->toArray());
        }

        $user = User::query()
            ->when(Schema::hasTable('bidders'), fn ($query) => $query->with('bidderProfile'))
            ->where('email', $request->email)
            ->first();

        if (! $user) {
            return $this->authResponse($request, false, 'No account found with that email. Please register first.', 'login', 422, null, [
                'email' => ['No account found with that email.'],
            ]);
        }

        if (! Hash::check($request->password, $user->password)) {
            return $this->authResponse($request, false, 'Account exists, but the password is incorrect.', 'login', 422, null, [
                'password' => ['Password is incorrect.'],
            ]);
        }

        if ($user->status === 'rejected' || ($user->role === 'bidder' && ! $user->canLoginAsBidder()) || ($user->role !== 'bidder' && $user->status !== 'active')) {
            return $this->authResponse($request, false, $this->accountUnavailableMessage($user), 'login', 422);
        }

        if ($this->loginCodePolicy($user) !== null) {
            if (! $this->issueLoginVerificationCode($request, $user, $request->boolean('remember'))) {
                return $this->authResponse($request, false, 'We could not send your verification code right now. Please try again in a few minutes, or contact the BAC Secretariat.', 'login', 503);
            }

            return $this->authResponse(
                $request,
                true,
                'Verification code sent to your email. Please enter the code to continue.',
                'verify',
                200,
                null,
                [],
                $this->loginVerificationPayload($user)
            );
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $this->queueBidderWelcome($request, $user);

        return $this->authResponse(
            $request,
            true,
            'Login successful.',
            'login',
            200,
            $this->redirectAfterLogin($request, $user),
            [],
            $this->welcomePayload($user)
        );
    }

    /** "Continue with Google" shows only once the OAuth client is configured. */
    public static function googleSignInEnabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /** Opened before the Google keys are set (e.g. typed in): back to the sign-in form with a reason. */
    private function googleSignInUnavailable()
    {
        return redirect()->route('home')
            ->with('error', 'Google sign-in is not available yet. Sign in with your email and password.')
            ->with('auth_tab', 'login');
    }

    public function redirectToGoogle(Request $request)
    {
        if (! self::googleSignInEnabled()) {
            return $this->googleSignInUnavailable();
        }
        if (Auth::check()) {
            return redirect()->to($this->redirectForUser(Auth::user()));
        }

        $request->session()->put('google_login_remember', $request->boolean('remember'));

        return Socialite::driver('google')
            ->redirectUrl(config('services.google.redirect') ?: route('auth.google.callback'))
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Signs in the account registered with this Google address. It never
     * creates one: bidders register with their documents and wait for the
     * BAC. Google has verified the address, so the bidder email code is not
     * needed; the account checks are the same as for a password sign-in.
     */
    public function handleGoogleCallback(Request $request)
    {
        if (! self::googleSignInEnabled()) {
            return $this->googleSignInUnavailable();
        }
        $fail = fn (string $message, string $tab = 'login') => redirect()->route('home')
            ->with('error', $message)
            ->with('auth_tab', $tab);

        if ($request->filled('error')) {
            return $fail('Google sign-in was cancelled.');
        }

        try {
            $google = Socialite::driver('google')
                ->redirectUrl(config('services.google.redirect') ?: route('auth.google.callback'))
                ->user();
        } catch (Throwable $exception) {
            report($exception);
            // Only the shape of the configured secret, never its value: Google client secrets start with GOCSPX-.
            $secret = (string) config('services.google.client_secret');
            error_log(sprintf('[google-signin] token exchange failed; client secret starts with GOCSPX-: %s, length: %d, contains *: %s',
                str_starts_with($secret, 'GOCSPX-') ? 'yes' : 'no', strlen($secret), str_contains($secret, '*') ? 'yes' : 'no'));

            return $fail('Google sign-in did not finish. Try again, or sign in with your email and password.');
        }

        $email = strtolower(trim((string) $google->getEmail()));
        $raw = (array) $google->getRaw();
        if ($email === '' || ! filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $fail('Your Google account email is not verified. Sign in with your email and password instead.');
        }

        $user = User::query()
            ->when(Schema::hasTable('bidders'), fn ($query) => $query->with('bidderProfile'))
            ->where('email', $email)
            ->first();

        if (! $user) {
            LoginAudit::record($request, null, 'google', 'failed', 'no_account');

            return $fail("No SJBAC account uses {$email}. Register as a bidder with this email first.", 'register');
        }

        if ($user->status === 'rejected' || ($user->role === 'bidder' && ! $user->canLoginAsBidder()) || ($user->role !== 'bidder' && $user->status !== 'active')) {
            LoginAudit::record($request, $user, 'google', 'failed', 'account_unavailable');

            return $fail($this->accountUnavailableMessage($user));
        }

        $remember = (bool) $request->session()->pull('google_login_remember', false);

        // Google proves the email, but an end-user office account still confirms each sign-in with the emailed code.
        if ($user->role === 'end_user') {
            if (! $this->issueLoginVerificationCode($request, $user, $remember)) {
                return $fail('We could not send your verification code right now. Please try again in a few minutes, or contact the BAC Secretariat.');
            }

            return redirect()->route('home')
                ->with('success', 'Verification code sent to your email. Please enter the code to continue.')
                ->with('auth_tab', 'verify')
                ->with('login_verification_prompt', $this->loginVerificationPayload($user));
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->queueBidderWelcome($request, $user);
        LoginAudit::record($request, $user, 'google', 'success');

        return redirect()->to($this->redirectAfterLogin($request, $user))->with('success', 'Signed in with Google.');
    }

    public function resendLoginCode(Request $request)
    {
        $pending = $request->session()->get(self::LOGIN_VERIFICATION_SESSION);

        if (! is_array($pending) || empty($pending['user_id'])) {
            return $this->authResponse($request, false, 'Please sign in again to request a new verification code.', 'login', 422);
        }

        $user = $this->pendingLoginUser($pending);

        if (! $user) {
            $request->session()->forget(self::LOGIN_VERIFICATION_SESSION);

            return $this->authResponse($request, false, 'Your account is not available for login.', 'login', 422);
        }

        $wait = $this->loginCodeResendWait($pending, $user);
        if ($wait > 0) {
            return $this->authResponse($request, false, "Please wait {$wait} seconds before requesting a new code.", 'verify', 429, null, [], [
                'requires_verification' => true,
                'email' => $user->email,
                'resend_available_in' => $wait,
            ]);
        }

        // A new code replaces the previous one, which stops working.
        if (! $this->issueLoginVerificationCode($request, $user, (bool) ($pending['remember'] ?? false))) {
            return $this->authResponse($request, false, 'We could not send your verification code right now. Please try again in a few minutes, or contact the BAC Secretariat.', 'verify', 503);
        }

        return $this->authResponse(
            $request,
            true,
            'New verification code sent to your email. The previous code no longer works.',
            'verify',
            200,
            null,
            [],
            $this->loginVerificationPayload($user)
        );
    }

    public function verifyLoginCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Verification code is required.',
            'code.digits' => 'Please enter the 6-digit verification code.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse($request, false, $validator->errors()->first(), 'verify', 422, null, $validator->errors()->toArray());
        }

        $pending = $request->session()->get(self::LOGIN_VERIFICATION_SESSION);

        if (! is_array($pending) || empty($pending['user_id']) || empty($pending['expires_at'])) {
            return $this->authResponse($request, false, 'Please sign in again to request a new verification code.', 'login', 422);
        }

        $user = $this->pendingLoginUser($pending);

        if (! $user) {
            $request->session()->forget(self::LOGIN_VERIFICATION_SESSION);

            return $this->authResponse($request, false, 'Your account is not available for login.', 'login', 422);
        }

        $policy = $this->loginCodePolicy($user);

        if (empty($pending['code_hash'])) {
            return $this->authResponse($request, false, 'This code can no longer be used. Press Resend code to get a new one.', 'verify', 422, null, [
                'code' => ['This code can no longer be used. Press Resend code to get a new one.'],
            ]);
        }

        if (now()->timestamp > (int) $pending['expires_at']) {
            if ($user->role === 'bidder') {
                $request->session()->forget(self::LOGIN_VERIFICATION_SESSION);

                return $this->authResponse($request, false, 'Verification code expired. Please sign in again.', 'login', 422);
            }

            $request->session()->put(self::LOGIN_VERIFICATION_SESSION, array_merge($pending, ['code_hash' => null]));

            return $this->authResponse($request, false, 'This code has expired. Press Resend code to get a new one.', 'verify', 422, null, [
                'code' => ['This code has expired. Press Resend code to get a new one.'],
            ]);
        }

        if (! Hash::check((string) $request->input('code'), (string) $pending['code_hash'])) {
            $attempts = (int) ($pending['attempts'] ?? 0) + 1;
            $maxAttempts = $policy['max_attempts'];

            if ($maxAttempts !== null && $attempts >= $maxAttempts) {
                // Too many tries: this code is spent. A new one can be requested.
                $request->session()->put(self::LOGIN_VERIFICATION_SESSION, array_merge($pending, ['code_hash' => null, 'attempts' => $attempts]));

                return $this->authResponse($request, false, 'Too many incorrect codes. Press Resend code to get a new one.', 'verify', 429, null, [
                    'code' => ['Too many incorrect codes. Press Resend code to get a new one.'],
                ]);
            }

            $request->session()->put(self::LOGIN_VERIFICATION_SESSION, array_merge($pending, ['attempts' => $attempts]));
            $message = $maxAttempts !== null
                ? 'Verification code is incorrect. '.($maxAttempts - $attempts).' '.Str::plural('attempt', $maxAttempts - $attempts).' left.'
                : 'Verification code is incorrect.';

            return $this->authResponse($request, false, $message, 'verify', 422, null, [
                'code' => [$message],
            ]);
        }

        $remember = (bool) ($pending['remember'] ?? false);
        // Single use: the code is gone as soon as it signs the user in.
        $request->session()->forget(self::LOGIN_VERIFICATION_SESSION);

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->queueBidderWelcome($request, $user);

        return $this->authResponse(
            $request,
            true,
            'Login successful.',
            'verify',
            200,
            $this->redirectAfterLogin($request, $user),
            [],
            $this->welcomePayload($user)
        );
    }

    public function showForgotPasswordForm()
    {
        return redirect()->route('home')->with('auth_tab', 'forgot');
    }

    public function sendPasswordResetLink(Request $request)
    {
        if (! $this->passwordResetAvailable()) {
            return $this->authResponse(
                $request,
                false,
                'Password reset is temporarily unavailable because the application database is not connected yet.',
                'forgot',
                503
            );
        }

        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse($request, false, $validator->errors()->first(), 'forgot', 422, null, $validator->errors()->toArray());
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return $this->authResponse($request, false, 'No account found with that email.', 'forgot', 422, null, [
                'email' => ['No account found with that email.'],
            ]);
        }

        $code = (string) random_int(100000, 999999);

        try {
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make($code),
                    'created_at' => now(),
                ]
            );
        } catch (Throwable $e) {
            report($e);

            return $this->authResponse($request, false, 'We could not prepare the password reset code right now. Please try again later or contact the SJBAC admin.', 'forgot', 503);
        }

        try {
            Mail::to($user->email)->send(new PasswordResetCodeMail($user, $code, self::PASSWORD_RESET_CODE_TTL_SECONDS));
        } catch (Throwable $e) {
            if (app()->isLocal() || config('app.env') === 'local') {
                return $this->authResponse(
                    $request,
                    true,
                    'Password reset email could not be sent, so here is your local test code: ' . $code,
                    'forgot_verify',
                    200,
                    null,
                    [],
                    [
                        'requires_password_code' => true,
                        'email' => $user->email,
                        'password_code_expires_in' => self::PASSWORD_RESET_CODE_TTL_SECONDS,
                        'dev_password_reset_code' => $code,
                    ]
                );
            }

            report($e);

            try {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            } catch (Throwable $cleanupError) {
                report($cleanupError);
            }

            return $this->authResponse($request, false, 'We could not send the password reset code right now. Please try again later or contact the SJBAC admin.', 'forgot', 503);
        }

        return $this->authResponse(
            $request,
            true,
            'Password reset code sent to your email.',
            'forgot_verify',
            200,
            null,
            [],
            [
                'requires_password_code' => true,
                'email' => $user->email,
                'password_code_expires_in' => self::PASSWORD_RESET_CODE_TTL_SECONDS,
            ]
        );
    }

    public function verifyPasswordResetCode(Request $request)
    {
        if (! $this->passwordResetAvailable()) {
            return $this->authResponse($request, false, 'Password reset is temporarily unavailable because the application database is not connected yet.', 'forgot', 503);
        }

        $request->merge([
            'email' => strtolower(trim((string) $request->email)),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'code.required' => 'Verification code is required.',
            'code.digits' => 'Please enter the 6-digit verification code.',
        ]);

        if ($validator->fails()) {
            return $this->authResponse($request, false, $validator->errors()->first(), 'forgot_verify', 422, null, $validator->errors()->toArray());
        }

        $reset = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (! $reset || ! Hash::check((string) $request->input('code'), (string) $reset->token)) {
            return $this->authResponse($request, false, 'Verification code is incorrect.', 'forgot_verify', 422, null, [
                'code' => ['Verification code is incorrect.'],
            ]);
        }

        if (! $reset->created_at || \Illuminate\Support\Carbon::parse($reset->created_at)->addSeconds(self::PASSWORD_RESET_CODE_TTL_SECONDS)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return $this->authResponse($request, false, 'Verification code expired. Please request a new code.', 'forgot_verify', 422, null, [
                'code' => ['Verification code expired. Please request a new code.'],
            ], [
                'password_code_expired' => true,
            ]);
        }

        $request->session()->put('verified_password_reset_email', $request->email);

        return $this->authResponse(
            $request,
            true,
            'Code verified. Create your new password.',
            'reset_password',
            200,
            null,
            [],
            [
                'password_reset_verified' => true,
                'email' => $request->email,
            ]
        );
    }

    public function showResetPasswordForm(Request $request, ?string $token = null)
    {
        $email = (string) $request->query('email', '');

        if ($token === null) {
            $email = (string) $request->session()->get('verified_password_reset_email', '');

            if ($email === '') {
                return redirect()
                    ->route('home')
                    ->with('error', 'Please verify your password reset code first.')
                    ->with('auth_tab', 'forgot');
            }
        }

        return view('auth.reset-password', [
            'token' => $token ?? '',
            'email' => $email,
        ]);
    }

    public function resetPassword(Request $request)
    {
        if (! $this->passwordResetAvailable()) {
            if ($request->ajax() || $request->expectsJson()) {
                return $this->authResponse(
                    $request,
                    false,
                    'Password reset is temporarily unavailable because the application database is not connected yet.',
                    'reset_password',
                    503,
                    null,
                    ['email' => ['Password reset is temporarily unavailable because the application database is not connected yet.']]
                );
            }

            return back()->withErrors([
                'email' => 'Password reset is temporarily unavailable because the application database is not connected yet.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'token' => ['nullable'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax() || $request->expectsJson()) {
                return $this->authResponse($request, false, $validator->errors()->first(), 'reset_password', 422, null, $validator->errors()->toArray());
            }

            return back()
                ->withInput($request->only(['email']))
                ->withErrors($validator);
        }

        $validated = $validator->validated();
        $email = strtolower(trim($validated['email']));
        $verifiedEmail = (string) $request->session()->get('verified_password_reset_email', '');

        if ($verifiedEmail !== '' && hash_equals($verifiedEmail, $email)) {
            $user = User::where('email', $email)->firstOrFail();
            $this->resetUserPassword($user, $validated['password']);
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            $request->session()->forget('verified_password_reset_email');

            if ($request->ajax() || $request->expectsJson()) {
                return $this->authResponse($request, true, 'Your password has been reset. Please sign in.', 'login');
            }

            return redirect()
                ->route('home')
                ->with('success', 'Your password has been reset. Please sign in.')
                ->with('auth_tab', 'login');
        }

        $status = Password::reset(
            [
                'email' => $email,
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'] ?? '',
            ],
            function (User $user, string $password): void {
                $this->resetUserPassword($user, $password);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            if ($request->ajax() || $request->expectsJson()) {
                return $this->authResponse($request, true, 'Your password has been reset. Please sign in.', 'login');
            }

            return redirect()
                ->route('home')
                ->with('success', 'Your password has been reset. Please sign in.')
                ->with('auth_tab', 'login');
        }

        if ($request->ajax() || $request->expectsJson()) {
            return $this->authResponse($request, false, __($status), 'reset_password', 422, null, [
                'email' => [__($status)],
            ]);
        }

        return back()
            ->withInput($request->only(['email']))
            ->withErrors(['email' => __($status)]);
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }

    protected function redirectForUser(User $user): string
    {
        return match ($user->role) {
            'admin' => route('admin.dashboard'),
            'staff' => route('staff.dashboard'),
            'end_user' => route('end-user.dashboard'),
            default => route('bidder.dashboard'),
        };
    }

    /**
     * Roles that confirm each sign-in with an emailed 6-digit code: how long a code
     * lasts, how long before another can be sent, and how many wrong tries it allows.
     *
     * @return array{ttl: int, cooldown: int, max_attempts: ?int}|null
     */
    protected function loginCodePolicy(User $user): ?array
    {
        return match ($user->role) {
            'bidder' => ['ttl' => 600, 'cooldown' => 0, 'max_attempts' => null],
            'end_user' => ['ttl' => 300, 'cooldown' => 60, 'max_attempts' => 5],
            default => null,
        };
    }

    /** The account a pending code belongs to, while it may still sign in. */
    protected function pendingLoginUser(array $pending): ?User
    {
        $user = User::query()
            ->when(Schema::hasTable('bidders'), fn ($query) => $query->with('bidderProfile'))
            ->find($pending['user_id'] ?? null);

        $available = match ($user?->role) {
            'bidder' => $user->canLoginAsBidder(),
            'end_user' => $user->status === 'active',
            default => false,
        };

        return $available && ($pending['role'] ?? $user->role) === $user->role ? $user : null;
    }

    protected function loginCodeResendWait(array $pending, User $user): int
    {
        $cooldown = $this->loginCodePolicy($user)['cooldown'] ?? 0;

        return max(0, (int) ($pending['sent_at'] ?? 0) + $cooldown - now()->timestamp);
    }

    protected function loginVerificationPayload(User $user): array
    {
        $policy = $this->loginCodePolicy($user);

        return [
            'requires_verification' => true,
            'email' => $user->email,
            'expires_in' => $policy['ttl'],
            'resend_available_in' => $policy['cooldown'],
        ];
    }

    /**
     * Emails a new code (only its hash is kept, in the session) and replaces any earlier one.
     * False when the code could not be emailed: the user sees a clear message, not a server error.
     */
    protected function issueLoginVerificationCode(Request $request, User $user, bool $remember): bool
    {
        $policy = $this->loginCodePolicy($user);
        $code = (string) random_int(100000, 999999);

        $request->session()->put(self::LOGIN_VERIFICATION_SESSION, [
            'user_id' => $user->id,
            'role' => $user->role,
            'remember' => $remember,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($policy['ttl'])->timestamp,
            'sent_at' => now()->timestamp,
            'attempts' => 0,
        ]);

        try {
            Mail::to($user->email)->send(new LoginVerificationCodeMail($user, $code, intdiv($policy['ttl'], 60)));
        } catch (Throwable $exception) {
            $request->session()->forget(self::LOGIN_VERIFICATION_SESSION);
            report($exception);

            return false;
        }

        return true;
    }

    protected function resetUserPassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));
    }

    protected function accountUnavailableMessage(User $user): string
    {
        if ($user->role === 'bidder') {
            $profile = null;

            if (Schema::hasTable('bidders')) {
                $profile = $user->relationLoaded('bidderProfile')
                    ? $user->bidderProfile
                    : $user->bidderProfile()->first();
            }

            if ($user->status === 'rejected' || $profile?->approval_status === 'rejected') {
                $reason = trim((string) ($profile?->rejection_reason ?? ''));

                return $reason !== ''
                    ? 'Your bidder registration was rejected. Reason: ' . $reason
                    : 'Your bidder registration was rejected by the SJBAC.';
            }

            return 'Your bidder registration is pending admin approval.';
        }

        if ($user->status === 'rejected') {
            return 'Your account registration was rejected by the administrator.';
        }

        return 'Your account already exists but is not active yet. Please wait for admin approval.';
    }


    protected function sendIncompleteBidderRequirementsEmail(Request $request, array $missingRequirements): void
    {
        if (
            $request->input('role') !== 'bidder'
            || $missingRequirements === []
            || ! filter_var($request->input('email'), FILTER_VALIDATE_EMAIL)
        ) {
            return;
        }

        try {
            Mail::to($request->input('email'))->send(new BidderIncompleteRequirementsMail(
                bidderName: (string) ($request->input('contact_person') ?: $request->input('company') ?: 'Bidder'),
                companyName: (string) ($request->input('company') ?: 'your company'),
                missingRequirements: $missingRequirements,
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function authTablesAvailable(): bool
    {
        try {
            return Schema::hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }

    protected function passwordResetAvailable(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('password_reset_tokens');
        } catch (Throwable) {
            return false;
        }
    }

}
