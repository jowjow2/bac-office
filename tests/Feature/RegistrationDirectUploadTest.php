<?php

use App\Models\BidderDocument;
use App\Models\User;
use App\Support\BidderRegistrationRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * A full set of eligibility documents is over the 4.5 MB a Vercel function
 * accepts (413), so on Vercel the registration documents go from the browser
 * straight to private Blob storage and the form sends their references. The
 * Blob API is faked here.
 */
beforeEach(function () {
    testCase()->withoutVite();
    Mail::fake();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');
    $this->rwToken = 'vercel_blob_rw_storeABC123_secretvalue';
    config()->set('services.vercel_blob', ['enabled' => true, 'token' => $this->rwToken]);
    $this->host = 'storeabc123.private.blob.vercel-storage.com';
    $this->folder = 'registration-uploads/'.str_repeat('a', 32);

    $this->token = fn (string $pathname) => testCase()->withSession(['registration_upload_folder' => $this->folder])
        ->postJson(route('register.upload-token'), ['type' => 'blob.generate-client-token', 'payload' => ['pathname' => $pathname, 'clientPayload' => null, 'multipart' => false]]);

    $this->form = fn (array $references) => [
        'role' => 'bidder', 'company' => 'Divine Company', 'registration_no' => '12233445', 'contact_person' => 'Juan Dela Cruz',
        'contact_number' => '09171234567', 'business_address' => 'San Jose, Occidental Mindoro', 'email' => 'divine@example.com',
        'password' => 'Secret-pass-2026', 'password_confirmation' => 'Secret-pass-2026',
        'uploaded_registration_documents' => $references,
        'uploaded_registration_document_names' => collect($references)->map(fn ($url, $key) => "{$key}.pdf")->all(),
    ];
    $this->references = fn (string $folder) => collect(BidderRegistrationRequirements::documents())
        ->mapWithKeys(fn ($document, $key) => [$key => "https://{$this->host}/{$folder}/{$key}-x1Y2z3.pdf"])->all();
});

it('issues upload tokens only for this browser\'s folder and only for PDF or images', function () {
    ($this->token)('registration-uploads/'.str_repeat('b', 32).'/permit.pdf')->assertStatus(422);
    ($this->token)($this->folder.'/permit.exe')->assertStatus(422);

    $clientToken = ($this->token)($this->folder.'/permit.pdf')->assertOk()->json('clientToken');
    [, $payload] = explode('.', base64_decode(substr($clientToken, strlen('vercel_blob_client_storeABC123_'))), 2);
    expect(json_decode(base64_decode($payload), true))
        ->toMatchArray(['pathname' => $this->folder.'/permit.pdf', 'allowedContentTypes' => ['application/pdf', 'image/jpeg', 'image/png']]);
});

it('registers a bidder from direct-upload references and deletes the temporary uploads', function () {
    $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF";
    $deleted = new ArrayObject;
    Http::fake(function (HttpRequest $request) use ($pdf, $deleted) {
        if ($request->method() === 'GET' && str_contains($request->url(), $this->host)) {
            return Http::response($pdf, 200, ['Content-Type' => 'application/pdf']);
        }
        if ($request->method() === 'POST' && str_ends_with($request->url(), '/api/blob/delete')) {
            foreach ($request->data()['urls'] as $url) {
                $deleted[] = $url;
            }

            return Http::response([], 200);
        }

        return Http::response([], 200);
    });

    testCase()->withSession(['registration_upload_folder' => $this->folder])
        ->postJson(route('register'), ($this->form)(($this->references)($this->folder)))
        ->assertOk()->assertJson(['ok' => true]);

    $user = User::where('email', 'divine@example.com')->firstOrFail();
    expect($user->role)->toBe('bidder')
        ->and(BidderDocument::where('user_id', $user->id)->count())->toBe(count(BidderRegistrationRequirements::documents()))
        ->and(count($deleted))->toBe(count(BidderRegistrationRequirements::documents()));
});

it('refuses references outside this browser\'s folder', function () {
    Http::fake();

    testCase()->withSession(['registration_upload_folder' => $this->folder])
        ->postJson(route('register'), ($this->form)(($this->references)('registration-uploads/'.str_repeat('b', 32))))
        ->assertStatus(422)->assertJson(['ok' => false]);

    expect(User::where('email', 'divine@example.com')->exists())->toBeFalse();
    Http::assertNothingSent();
});

it('offers direct uploads in the register form only when Blob storage is set up', function () {
    testCase()->get('/')->assertOk()->assertSee('data-direct-upload-url="'.route('register.upload-token').'"', false);

    config()->set('services.vercel_blob', ['enabled' => false, 'token' => null]);
    testCase()->get('/')->assertOk()->assertDontSee('data-direct-upload-url', false);
});
