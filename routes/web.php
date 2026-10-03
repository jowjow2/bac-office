<?php

use App\Models\Award;
use App\Models\Project;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BiddingFeePaymentController;
use App\Http\Controllers\BiddingTrackController;
use App\Http\Controllers\BidderController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicAwardController;
use App\Http\Controllers\PublicBidderController;
use App\Http\Controllers\PublicProcurementController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProcurementLifecycleController;
use App\Http\Controllers\ProcurementRequestController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

// Scheduled procurement transitions without a page visit (Vercel Cron, see vercel.json).
// Idempotent: it only does what the saved schedule and server time already require.
Route::get('/cron/procurement-schedule', function (Request $request) {
    $secret = config('services.cron.secret');
    abort_if(filled($secret) && ! hash_equals('Bearer '.$secret, (string) $request->header('Authorization')), 401);

    return response()->json(['opened' => app(\App\Support\BidOpening::class)->openDueTechnicalProjects()]);
})->name('cron.procurement-schedule');

Route::get('/', function () {
    try {
        $hasProjectsTable = Schema::hasTable('projects');
        $hasAwardsTable = Schema::hasTable('awards');

        $publicProjectsCount = $hasProjectsTable
            ? Project::query()->visibleToPublic()->count()
            : 0;
        $openProjectsCount = $hasProjectsTable
            ? Project::query()->visibleToPublic()->where('status', 'open')->count()
            : 0;
        $awardedContractsCount = $hasAwardsTable
            ? Award::query()->publiclyPosted()->count()
            : 0;
        $totalAwardedValue = $hasAwardsTable
            ? (float) Award::query()->publiclyPosted()->sum('contract_amount')
            : 0.0;

        $latestProjects = $hasProjectsTable
            ? Project::query()
                ->visibleToPublic()
                ->withCount('bids')
                ->latest()
                ->take(3)
                ->get()
            : collect();

        $latestAwards = $hasAwardsTable
            ? Award::query()->publiclyPosted()
                ->with(['project:id,title', 'bid.user:id,name,company'])
                ->latest('contract_date')
                ->take(3)
                ->get()
            : collect();
    } catch (Throwable) {
        $publicProjectsCount = 0;
        $openProjectsCount = 0;
        $awardedContractsCount = 0;
        $totalAwardedValue = 0.0;
        $latestProjects = collect();
        $latestAwards = collect();
    }

    return view('pages.home', compact(
        'publicProjectsCount',
        'openProjectsCount',
        'awardedContractsCount',
        'totalAwardedValue',
        'latestProjects',
        'latestAwards',
    ));
})->name('home');

Route::get('/about', function () {
    return view('pages.about');
});

Route::get('/profile', function () {
    return view('pages.profile');
});

Route::get('/contact', function () {
    return view('pages.contact');
});

// Linked from the sign-in form and required by Google for "Continue with Google".
Route::view('/privacy', 'pages.privacy')->name('privacy');

Route::get('/procurement', [PublicProcurementController::class, 'index'])->name('public.procurement');
Route::get('/procurement/projects/{project}/documents/{document}', [PublicProcurementController::class, 'previewDocument'])->name('public.procurement.document.preview');
Route::get('/procurement/projects/{project}/documents/{document}/pdf', [PublicProcurementController::class, 'streamDocumentPdf'])->name('public.procurement.document.pdf');
Route::get('/procurement/projects/{project}/qr.svg', [PublicProcurementController::class, 'qr'])->name('public.procurement.qr');
Route::get('/procurement/projects/{project}', [PublicProcurementController::class, 'show'])->name('public.procurement.show');

// Files on a private Vercel Blob disk, through the short-lived signed links its temporaryUrl() builds.
Route::get('/files/{disk}/{path}', function (string $disk, string $path) {
    abort_unless(in_array($disk, ['local', 'public'], true) && config("filesystems.disks.$disk.driver") === 'vercel-blob', 404);
    abort_if(str_contains($path, '..'), 404);
    $storage = \Illuminate\Support\Facades\Storage::disk($disk);
    abort_unless($storage->exists($path), 404);

    return $storage->response($path, basename($path), [
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-cache',
    ]);
})->where('path', '.*')->middleware('signed')->name('files.blob');

// Public award verification is read-only and always loads the award record fresh.
Route::get('/certificate/verify/{award}', [CertificateController::class, 'verify'])->name('certificate.verify');

// Public certificate access by token only (secure)
Route::get('/certificate/view/{token}', [CertificateController::class, 'view'])->name('certificate.view');
Route::get('/certificate/{token}', [CertificateController::class, 'view'])->name('public.certificate.view');
Route::get('/qr/{token}.svg', [PublicAwardController::class, 'qrByToken'])->name('public.qr.show');

Route::get('/awards', [PublicAwardController::class, 'index'])->name('public.awards');
Route::get('/awards/document/{token}', [PublicAwardController::class, 'showByToken'])->name('public.awards.document');

// Public bidder verification (read-only) - lets anyone who scans a bidder's profile
// QR code see that bidder's approved bids and awarded contracts.
Route::get('/bidder/verify/{token}', [PublicBidderController::class, 'verify'])->name('public.bidder.verify');
Route::get('/bidder/qr/{token}.svg', [PublicBidderController::class, 'qrByToken'])->name('public.bidder.qr');


Route::get('/menu', function () {
    return view('menu');
});

Route::view('/slider', 'slider')->name('slider');

// Homepage 



Route::get('/login', [AuthController::class, 'showLoginPage'])->name('login.page');
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login/verify-code', [AuthController::class, 'verifyLoginCode'])->name('login.verify-code');
Route::post('/login/resend-code', [AuthController::class, 'resendLoginCode'])->name('login.resend-code');
Route::post('/register', [AuthController::class, 'register'])->name('register');
// Registration documents go from the browser straight to Blob storage (413 above 4.5 MB per request).
Route::post('/register/uploads', [AuthController::class, 'registrationUploadToken'])->middleware('throttle:60,1')->name('register.upload-token');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
// "Continue with Google": only for accounts already registered with that Gmail address.
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->middleware('throttle:20,1')->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->middleware('throttle:20,1')->name('auth.google.callback');
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->name('password.email');
    Route::post('/forgot-password/verify-code', [AuthController::class, 'verifyPasswordResetCode'])->name('password.verify-code');
    Route::get('/reset-password', [AuthController::class, 'showResetPasswordForm'])->name('password.reset.verified');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::get('/messages/{message}/attachment', [MessageController::class, 'attachment'])->name('messages.attachment');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // Procurement files: each action checks the viewer may see the file.
    Route::get('/procurement-files/proceedings/{proceeding}', [ProcurementLifecycleController::class, 'proceedingFile'])->name('procurement.files.proceeding');
    Route::get('/procurement-files/decisions/{tracking}', [ProcurementLifecycleController::class, 'decisionFile'])->name('procurement.files.decision');
    Route::get('/procurement-files/requests/{document}', [ProcurementLifecycleController::class, 'requestFile'])->name('procurement.files.request');
    Route::get('/contract-implementation-events/{event}/document', [\App\Http\Controllers\ContractImplementationController::class, 'document'])->name('contract-implementation.document');
    Route::get('/infrastructure-contract-events/{event}/document', [\App\Http\Controllers\InfrastructureImplementationController::class, 'document'])->name('infrastructure.document');
});


// End-user offices: procurement requests of their own office.
Route::middleware(['auth', 'end_user'])->prefix('end-user')->name('end-user.')->group(function () {
    Route::get('/dashboard', [ProcurementRequestController::class, 'dashboard'])->name('dashboard');
    Route::get('/infrastructure-contracts', [\App\Http\Controllers\InfrastructureImplementationController::class, 'endUserIndex'])->name('infrastructure.index');
    Route::post('/infrastructure-contracts/{award}/inspect', [\App\Http\Controllers\InfrastructureImplementationController::class, 'inspect'])->name('infrastructure.inspect');
    Route::get('/notifications', [ProcurementRequestController::class, 'notifications'])->name('notifications');
    Route::get('/requests', [ProcurementRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [ProcurementRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [ProcurementRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{procurementRequest}', [ProcurementRequestController::class, 'show'])->name('requests.show');
    Route::get('/requests/{procurementRequest}/edit', [ProcurementRequestController::class, 'edit'])->name('requests.edit');
    Route::put('/requests/{procurementRequest}', [ProcurementRequestController::class, 'update'])->name('requests.update');
    Route::post('/requests/{procurementRequest}/submit', [ProcurementRequestController::class, 'submit'])->name('requests.submit');
    Route::delete('/requests/{procurementRequest}/documents/{document}', [ProcurementRequestController::class, 'destroyDocument'])->name('requests.documents.destroy');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/projects', [AdminController::class, 'projects'])->name('admin.projects');
    Route::post('/admin/projects/export', [AdminController::class, 'exportProjects'])->name('admin.projects.export');
    Route::get('/admin/projects/create', [AdminController::class, 'createProject'])->name('admin.projects.create');
    Route::post('/admin/projects', [AdminController::class, 'storeProject'])->name('admin.projects.store');
    Route::post('/admin/projects/wizard/store', [AdminController::class, 'storeProjectWizard'])->name('admin.projects.wizard.store');
    Route::get('/admin/projects/{project}/files', [AdminController::class, 'projectFiles'])->name('admin.project.files');
    Route::delete('/admin/projects/{project}/documents/{document}', [AdminController::class, 'destroyProjectDocument'])->name('admin.project.document.destroy');
    Route::get('/admin/projects/{project}', [AdminController::class, 'viewProject'])->name('admin.project.view');
    Route::post('/admin/projects/{project}/failed-bidding', [AdminController::class, 'declareFailedBidding'])->name('admin.project.failed-bidding');
    Route::get('/admin/projects/{project}/documents/{document}/pdf', [AdminController::class, 'streamProjectDocumentPdf'])->name('admin.project.document.pdf');
    Route::get('/admin/projects/{project}/edit', [AdminController::class, 'editProject'])->name('admin.project.edit');
    Route::match(['put', 'post'], '/admin/projects/{project}', [AdminController::class, 'updateProject'])->name('admin.project.update');
    Route::post('/admin/projects/{project}/archive', [AdminController::class, 'archiveProject'])->name('admin.project.archive');
    Route::delete('/admin/projects/{project}', [AdminController::class, 'destroyProject'])->name('admin.project.destroy');
    Route::post('/admin/projects/{project}/publish', [AdminController::class, 'publishProject'])->name('admin.project.publish');
    Route::get('/admin/requests', [ProcurementRequestController::class, 'queue'])->name('admin.requests');
    Route::post('/admin/requests/{procurementRequest}/review', [ProcurementRequestController::class, 'review'])->name('admin.requests.review');
    Route::get('/admin/procurements/{project}', [ProcurementLifecycleController::class, 'show'])->name('admin.procurement.show');
    Route::post('/admin/procurements/{project}/publication', [ProcurementLifecycleController::class, 'recordPublication'])->name('admin.procurement.publication');
    Route::post('/admin/procurements/{project}/proceedings', [ProcurementLifecycleController::class, 'recordProceeding'])->name('admin.procurement.proceedings');
    Route::post('/admin/procurements/{project}/inspection', [ProcurementLifecycleController::class, 'recordInspection'])->name('admin.procurement.inspection');
    Route::post('/admin/procurements/{project}/acceptance', [ProcurementLifecycleController::class, 'recordAcceptance'])->name('admin.procurement.acceptance');
    Route::get('/admin/payments', [BiddingFeePaymentController::class, 'index'])->name('admin.payments');
    Route::post('/admin/payments', [BiddingFeePaymentController::class, 'store'])->name('admin.payments.store');
    Route::put('/admin/payments/{payment}', [BiddingFeePaymentController::class, 'update'])->name('admin.payments.update');
    Route::delete('/admin/payments/{payment}', [BiddingFeePaymentController::class, 'destroy'])->name('admin.payments.destroy');
    Route::get('/admin/bidders', [AdminController::class, 'allBids'])->name('admin.bids');
    Route::post('/admin/bids/bulk', [AdminController::class, 'bulkBids'])->name('admin.bids.bulk');
    Route::post('/admin/bids/export', [AdminController::class, 'exportBids'])->name('admin.bids.export');
    Route::get('/admin/bids/{bid}', [AdminController::class, 'viewBid'])->name('admin.bid.view');
    Route::get('/admin/bids/{bid}/documents/{document}/pdf', [AdminController::class, 'streamBidDocumentPdf'])->name('admin.bid.document.pdf');
    Route::get('/admin/bids/{bid}/documents/{document}', [AdminController::class, 'previewBidDocument'])->name('admin.bid.document.preview');
    Route::get('/admin/bids/{bid}/award-recommendation/{document}', [\App\Http\Controllers\AwardRecommendationDocumentController::class, 'download'])->whereIn('document', ['resolution', 'post-qualification-report'])->name('admin.bid.award-recommendation.document');
    Route::get('/admin/bids/{bid}/edit', [AdminController::class, 'editBid'])->name('admin.bid.edit');
    Route::put('/admin/bids/{bid}', [AdminController::class, 'updateBid'])->name('admin.bid.update');
    Route::post('/admin/bids/{bid}/decisions', [AdminController::class, 'recordBidDecision'])->name('admin.bid.decision');
    Route::post('/admin/bids/{bid}/document-review', [\App\Http\Controllers\BidDocumentReviewController::class, 'reviewSubmission'])->name('admin.bid.documents.review-submission');
    Route::get('/admin/bids/{bid}/files/{bidDocument}', [AdminController::class, 'streamBidComponentFile'])->name('admin.bid.component-file');
    Route::post('/admin/bids/{bid}/documents/{bidDocument}/review', [\App\Http\Controllers\BidDocumentReviewController::class, 'review'])->name('admin.bid.document.review');
    Route::post('/admin/projects/{project}/submission-settings', [AdminController::class, 'updateSubmissionSettings'])->name('admin.project.submission-settings');
    Route::post('/admin/projects/{project}/bid-opening-rules', [AdminController::class, 'configureBidOpening'])->name('admin.project.bid-opening-rules');
    Route::post('/admin/bids/{bid}/technical-score', [AdminController::class, 'recordBidTechnicalScore'])->name('admin.bid.technical-score');
    Route::post('/admin/bids/{bid}/open-financial', [AdminController::class, 'openBidFinancial'])->name('admin.bid.open-financial');
    Route::post('/admin/projects/{project}/open-bids', [AdminController::class, 'openProjectBids'])->name('admin.project.open-bids');
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/admin/users/{user}/review', [AdminController::class, 'reviewUser'])->name('admin.users.review');
    Route::get('/admin/users/{user}/login-activity', [AdminController::class, 'userLoginActivity'])->name('admin.users.login-activity');
    Route::get('/admin/users/{user}/documents/{document}', [AdminController::class, 'previewBidderDocument'])->name('admin.user.document.preview');
    Route::get('/admin/users/{user}/documents/{document}/pdf', [AdminController::class, 'streamBidderDocumentPdf'])->name('admin.user.document.pdf');
    Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::patch('/admin/users/{user}/approve', [AdminController::class, 'approveUser'])->name('admin.users.approve');
    Route::patch('/admin/users/{user}/reject', [AdminController::class, 'rejectUser'])->name('admin.users.reject');
    Route::post('/admin/users/{user}/requirements', [AdminController::class, 'requestBidderRequirements'])->name('admin.users.requirements');
    Route::post('/admin/users/{user}/requirements/resend', [AdminController::class, 'resendBidderRequirementsNotification'])->name('admin.users.requirements.resend');
    Route::post('/admin/users/{user}/requirements/incomplete-email', [AdminController::class, 'sendIncompleteRequirementsEmail'])->name('admin.users.requirements.incomplete-email');
    Route::patch('/admin/users/{user}/sanction', [AdminController::class, 'sanctionUser'])->name('admin.users.sanction');
    Route::patch('/admin/users/{user}/sanction/lift', [AdminController::class, 'liftBidderSanction'])->name('admin.users.sanction.lift');
    Route::delete('/admin/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
    Route::get('/admin/assignments', [AdminController::class, 'assignments'])->name('admin.assignments');
    Route::post('/admin/assignments', [AdminController::class, 'storeAssignment'])->name('admin.assignments.store');
    Route::delete('/admin/assignments/{assignment}', [AdminController::class, 'destroyAssignment'])->name('admin.assignments.destroy');
    Route::get('/admin/reports', [AdminController::class, 'reports'])->name('admin.reports');
    Route::get('/admin/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('admin.audit-logs');
    Route::get('/admin/audit-logs/export', [\App\Http\Controllers\AuditLogController::class, 'export'])->name('admin.audit-logs.export');
    Route::get('/admin/reports/export/csv', [AdminController::class, 'exportReportsCsv'])->name('admin.reports.export.csv');
    Route::get('/admin/reports/export/print', [AdminController::class, 'printReports'])->name('admin.reports.print');
    Route::get('/admin/notifications', [AdminController::class, 'notifications'])->name('admin.notifications');
    Route::post('/admin/notifications/read-all', [AdminController::class, 'markAllNotificationsRead'])->name('admin.notifications.read-all');
    Route::post('/admin/notifications/{notificationId}/read', [AdminController::class, 'markNotificationRead'])->name('admin.notifications.read');
    Route::get('/admin/messages', [MessageController::class, 'adminIndex'])->name('admin.messages');
    Route::get('/admin/messages/status-sync', [MessageController::class, 'adminStatusSync'])->name('admin.messages.status-sync');
    Route::get('/admin/messages/conversation-sync', [MessageController::class, 'adminConversationSync'])->name('admin.messages.conversation-sync');
    Route::post('/admin/messages/typing', [MessageController::class, 'adminTyping'])->name('admin.messages.typing');
    Route::post('/admin/messages', [MessageController::class, 'adminStore'])->name('admin.messages.store');

    Route::put('/admin/demo-clock', [\App\Http\Controllers\DemoClockController::class, 'update'])->name('admin.demo-clock.update');
    Route::delete('/admin/demo-clock', [\App\Http\Controllers\DemoClockController::class, 'reset'])->name('admin.demo-clock.reset');
    Route::get('/admin/awards', [AdminController::class, 'awards'])->name('admin.awards.index');
    Route::get('/admin/awards/{award}/infrastructure', [\App\Http\Controllers\InfrastructureImplementationController::class, 'showAdmin'])->name('admin.infrastructure.show');
    Route::put('/admin/awards/{award}/infrastructure', [\App\Http\Controllers\InfrastructureImplementationController::class, 'configure'])->name('admin.infrastructure.configure');
    Route::post('/admin/awards/{award}/infrastructure/action', [\App\Http\Controllers\InfrastructureImplementationController::class, 'action'])->name('admin.infrastructure.action');
    Route::put('/admin/awards/{award}/contract-implementation', [\App\Http\Controllers\ContractImplementationController::class, 'configure'])->name('admin.contract-implementation.configure');
    Route::post('/admin/awards/{award}/contract-implementation/action', [\App\Http\Controllers\ContractImplementationController::class, 'action'])->name('admin.contract-implementation.action');
    Route::get('/admin/awards/{award}', [AdminController::class, 'viewAward'])->name('admin.award.view');
    Route::get('/admin/projects/{project}/award', [AdminController::class, 'createAward'])->name('admin.project.award');
    Route::post('/admin/awards/declare/{project}', [AdminController::class, 'declareWinner'])->name('admin.awards.declare');
    Route::post('/admin/awards', [AdminController::class, 'storeAward'])->name('admin.awards.store');
    Route::post('/admin/awards/{award}/certificate/upload', [AdminController::class, 'uploadCertificate'])->name('admin.awards.certificate.upload');
    Route::post('/admin/awards/{award}/certificate/replace', [AdminController::class, 'replaceCertificate'])->name('admin.awards.certificate.replace');
    Route::post('/admin/awards/{award}/revoke', [AdminController::class, 'revokeCertificate'])->name('admin.awards.revoke');
    Route::post('/admin/awards/{award}/cancel', [AdminController::class, 'cancelAward'])->name('admin.awards.cancel');
    Route::post('/admin/awards/{award}/regenerate-token', [AdminController::class, 'regenerateQrToken'])->name('admin.awards.regenerate.token');

    // Procurement management
    Route::get('/procurements', [ProcurementController::class, 'index'])->name('procurements.index');
    Route::get('/procurements/publish', [ProcurementController::class, 'publish'])->name('procurements.publish');
});

Route::middleware(['auth', 'staff'])->group(function () {
    Route::post('/staff/bids/{bid}/document-review', [\App\Http\Controllers\BidDocumentReviewController::class, 'reviewSubmission'])->name('staff.bid.documents.review-submission');
    Route::post('/staff/bids/{bid}/documents/{bidDocument}/review', [\App\Http\Controllers\BidDocumentReviewController::class, 'review'])->name('staff.bid.document.review');
    Route::get('/staff/dashboard', [StaffController::class, 'index'])->name('staff.dashboard');
    Route::get('/staff/assign-projects', [StaffController::class, 'assignProjects'])->name('staff.assign-projects');
    Route::patch('/staff/projects/{project}/status', [StaffController::class, 'updateProjectStatus'])->name('staff.projects.status');
    Route::post('/staff/projects/{project}/open-bids', [StaffController::class, 'openProjectBids'])->name('staff.projects.open-bids');
    Route::get('/staff/requests', [ProcurementRequestController::class, 'queue'])->name('staff.requests');
    Route::post('/staff/requests/{procurementRequest}/review', [ProcurementRequestController::class, 'review'])->name('staff.requests.review');
    Route::get('/staff/procurements/{project}', [ProcurementLifecycleController::class, 'show'])->name('staff.procurement.show');
    Route::get('/staff/awards/{award}/infrastructure', [\App\Http\Controllers\InfrastructureImplementationController::class, 'showStaff'])->name('staff.infrastructure.show');
    Route::put('/staff/awards/{award}/infrastructure', [\App\Http\Controllers\InfrastructureImplementationController::class, 'configure'])->name('staff.infrastructure.configure');
    Route::post('/staff/awards/{award}/infrastructure/action', [\App\Http\Controllers\InfrastructureImplementationController::class, 'action'])->name('staff.infrastructure.action');
    Route::put('/staff/awards/{award}/contract-implementation', [\App\Http\Controllers\ContractImplementationController::class, 'configure'])->name('staff.contract-implementation.configure');
    Route::post('/staff/awards/{award}/contract-implementation/action', [\App\Http\Controllers\ContractImplementationController::class, 'action'])->name('staff.contract-implementation.action');
    Route::post('/staff/procurements/{project}/publication', [ProcurementLifecycleController::class, 'recordPublication'])->name('staff.procurement.publication');
    Route::post('/staff/procurements/{project}/proceedings', [ProcurementLifecycleController::class, 'recordProceeding'])->name('staff.procurement.proceedings');
    Route::post('/staff/procurements/{project}/inspection', [ProcurementLifecycleController::class, 'recordInspection'])->name('staff.procurement.inspection');
    Route::post('/staff/procurements/{project}/acceptance', [ProcurementLifecycleController::class, 'recordAcceptance'])->name('staff.procurement.acceptance');
    Route::get('/staff/payments', [BiddingFeePaymentController::class, 'index'])->name('staff.payments');
    Route::post('/staff/payments', [BiddingFeePaymentController::class, 'store'])->name('staff.payments.store');
    Route::put('/staff/payments/{payment}', [BiddingFeePaymentController::class, 'update'])->name('staff.payments.update');
    Route::delete('/staff/payments/{payment}', [BiddingFeePaymentController::class, 'destroy'])->name('staff.payments.destroy');
    Route::get('/staff/review-bids', [StaffController::class, 'reviewBids'])->name('staff.review-bids');
    Route::get('/staff/review-bids/{bid}', [StaffController::class, 'getBidDetails'])->name('staff.review-bids.show');
    Route::post('/staff/review-bids/{bid}/validate', [StaffController::class, 'validateBidDocuments'])->name('staff.review-bids.validate');
    Route::post('/staff/review-bids/{bid}/reject', [StaffController::class, 'rejectBid'])->name('staff.review-bids.reject');
    Route::get('/staff/bids/{bid}/details', [StaffController::class, 'getBidDetails'])->name('staff.bids.details');
    Route::get('/staff/reports', [StaffController::class, 'reports'])->name('staff.reports');
    Route::get('/staff/reports/export/csv', [StaffController::class, 'exportReportsCsv'])->name('staff.reports.export.csv');
    Route::get('/staff/reports/export/print', [StaffController::class, 'printReports'])->name('staff.reports.print');
    Route::get('/staff/notifications', [StaffController::class, 'notifications'])->name('staff.notifications');
    Route::post('/staff/notifications/read-all', [StaffController::class, 'markAllNotificationsRead'])->name('staff.notifications.read-all');
    Route::get('/staff/bids/{bid}/proposal', [StaffController::class, 'downloadBidProposal'])->name('staff.bids.proposal.download');
    Route::get('/staff/bids/{bid}/proposal/preview', [StaffController::class, 'previewBidProposal'])->name('staff.bids.proposal.preview');
    Route::get('/staff/bids/{bid}/eligibility', [StaffController::class, 'downloadBidEligibility'])->name('staff.bids.eligibility.download');
    Route::get('/staff/bids/{bid}/eligibility/preview', [StaffController::class, 'previewBidEligibility'])->name('staff.bids.eligibility.preview');
    Route::get('/staff/bids/{bid}/documents/{document}/pdf', [StaffController::class, 'streamBidderDocumentPdf'])->name('staff.bids.documents.pdf');
    Route::patch('/staff/bids/{bid}/validate', [StaffController::class, 'validateBidDocuments'])->name('staff.bids.validate');
    Route::patch('/staff/bids/{bid}/eligibility', [StaffController::class, 'updateBidEligibility'])->name('staff.bids.eligibility');
    Route::patch('/staff/bids/{bid}/evaluate', [StaffController::class, 'evaluateBid'])->name('staff.bids.evaluate');
    Route::patch('/staff/bids/{bid}/reject', [StaffController::class, 'rejectBid'])->name('staff.bids.reject');
    Route::get('/staff/bids/{bid}/clarification', [StaffController::class, 'requestBidClarification'])->name('staff.bids.clarification');
    Route::post('/staff/bids/{bid}/recommend', [StaffController::class, 'recommendBid'])->name('staff.bids.recommend');

    Route::get('/staff/messages', [MessageController::class, 'staffIndex'])->name('staff.messages');
    Route::get('/staff/messages/status-sync', [MessageController::class, 'staffStatusSync'])->name('staff.messages.status-sync');
    Route::get('/staff/messages/conversation-sync', [MessageController::class, 'staffConversationSync'])->name('staff.messages.conversation-sync');
    Route::post('/staff/messages/typing', [MessageController::class, 'staffTyping'])->name('staff.messages.typing');
    Route::post('/staff/messages', [MessageController::class, 'staffStore'])->name('staff.messages.store');
    Route::get('/staff/users/{user}/review', [AdminController::class, 'reviewUser'])->name('staff.users.review');
    Route::get('/staff/users/{user}/login-activity', [AdminController::class, 'userLoginActivity'])->name('staff.users.login-activity');
    Route::get('/staff/users/{user}/documents/{document}', [AdminController::class, 'previewBidderDocument'])->name('staff.user.document.preview');
    Route::get('/staff/users/{user}/documents/{document}/pdf', [AdminController::class, 'streamBidderDocumentPdf'])->name('staff.user.document.pdf');
    Route::patch('/staff/users/{user}/approve', [AdminController::class, 'approveUser'])->name('staff.users.approve');
    Route::patch('/staff/users/{user}/reject', [AdminController::class, 'rejectUser'])->name('staff.users.reject');
    Route::post('/staff/users/{user}/requirements', [AdminController::class, 'requestBidderRequirements'])->name('staff.users.requirements');
    Route::post('/staff/users/{user}/requirements/resend', [AdminController::class, 'resendBidderRequirementsNotification'])->name('staff.users.requirements.resend');
    Route::patch('/staff/users/{user}/sanction', [AdminController::class, 'sanctionUser'])->name('staff.users.sanction');
    Route::patch('/staff/users/{user}/sanction/lift', [AdminController::class, 'liftBidderSanction'])->name('staff.users.sanction.lift');
});

Route::middleware(['auth', 'bidder'])->group(function () {
    Route::get('/bidder/dashboard', [BidderController::class, 'index'])->name('bidder.dashboard');
    Route::get('/bidder/company-profile', [BidderController::class, 'companyProfile'])->name('bidder.company-profile');
    Route::get('/bidder/notifications', [BidderController::class, 'notifications'])->name('bidder.notifications');
    Route::get('/bidder/messages', [MessageController::class, 'bidderIndex'])->name('bidder.messages');
    Route::get('/bidder/messages/status-sync', [MessageController::class, 'bidderStatusSync'])->name('bidder.messages.status-sync');
    Route::get('/bidder/messages/conversation-sync', [MessageController::class, 'bidderConversationSync'])->name('bidder.messages.conversation-sync');
    Route::post('/bidder/messages/typing', [MessageController::class, 'bidderTyping'])->name('bidder.messages.typing');
    Route::post('/bidder/messages', [MessageController::class, 'bidderStore'])->name('bidder.messages.store');
    Route::post('/bidder/notifications/read-all', [BidderController::class, 'markAllNotificationsRead'])->name('bidder.notifications.read-all');
    Route::patch('/bidder/profile', [BidderController::class, 'updateProfile'])->name('bidder.profile.update');
    Route::post('/bidder/company-profile/documents', [BidderController::class, 'uploadDocument'])->name('bidder.documents.store');
    Route::post('/bidder/company-profile/submit-for-re-evaluation', [BidderController::class, 'submitForReevaluation'])->name('bidder.requirements.reevaluate');
    Route::get('/bidder/company-profile/documents/{document}', [BidderController::class, 'previewDocument'])->name('bidder.document.preview');
    Route::get('/bidder/company-profile/documents/{document}/pdf', [BidderController::class, 'streamOwnDocumentPdf'])->name('bidder.document.pdf');
});

Route::middleware(['auth', 'approved.bidder'])->group(function () {
    Route::get('/bidder/available-projects', [BidderController::class, 'availableProjects'])->name('bidder.available-projects');
    Route::get('/bidder/opportunities/{project}', [BidderController::class, 'showOpportunity'])->name('bidder.opportunities.show');
    Route::get('/bidder/projects/{project}/documents/{document}', [BidderController::class, 'previewProjectDocument'])->name('bidder.project.document.preview');
    Route::get('/bidder/projects/{project}/documents/{document}/pdf', [BidderController::class, 'streamProjectDocumentPdf'])->name('bidder.project.document.pdf');
    Route::get('/bidder/my-bids', [BidderController::class, 'myBids'])->name('bidder.my-bids');
    Route::get('/bidding-track', [BiddingTrackController::class, 'index'])->name('bidder.bidding-track');
    Route::get('/bidding-track/data', [BiddingTrackController::class, 'data'])->name('bidder.bidding-track.data');
    Route::get('/bidder/awarded-contracts', [BidderController::class, 'awardedContracts'])->name('bidder.awarded-contracts');
    Route::get('/bidder/awards/{award}/infrastructure', [\App\Http\Controllers\InfrastructureImplementationController::class, 'showBidder'])->name('bidder.infrastructure.show');
    Route::post('/bidder/awards/{award}/infrastructure/progress', [\App\Http\Controllers\InfrastructureImplementationController::class, 'progress'])->name('bidder.infrastructure.progress');
    Route::post('/bidder/awards/{award}/contract-implementation/delivery', [\App\Http\Controllers\ContractImplementationController::class, 'supplierDelivery'])->name('bidder.contract-implementation.delivery');
    Route::post('/bidder/bids/{bid}/documents/{bidDocument}/replacement', [\App\Http\Controllers\BidDocumentReviewController::class, 'replace'])->name('bidder.bid-document.replace');
    Route::post('/bidder/projects/{project}/bids', [BidderController::class, 'submitBid'])->name('bidder.bids.store');
    Route::post('/bidder/projects/{project}/bid-uploads', [BidderController::class, 'bidUploadToken'])->name('bidder.bids.upload-token');
});



// procurementController


// ACCOUNT



//admin route
