<?php

use App\Models\Award;
use App\Models\Project;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BiddingTrackController;
use App\Http\Controllers\BidderController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicAwardController;
use App\Http\Controllers\PublicProcurementController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

Route::get('/', function () {
    try {
        $hasProjectsTable = Schema::hasTable('projects');
        $hasAwardsTable = Schema::hasTable('awards');

        $publicProjectsCount = $hasProjectsTable
            ? Project::query()->visibleToPublic()->count()
            : 0;
        $openProjectsCount = $hasProjectsTable
            ? Project::query()->where('status', 'open')->count()
            : 0;
        $awardedContractsCount = $hasAwardsTable
            ? Award::query()->count()
            : 0;
        $totalAwardedValue = $hasAwardsTable
            ? (float) Award::query()->sum('contract_amount')
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
            ? Award::query()
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

Route::get('/procurement', [PublicProcurementController::class, 'index'])->name('public.procurement');
Route::get('/procurement/projects/{project}/documents/{document}', [PublicProcurementController::class, 'previewDocument'])->name('public.procurement.document.preview');
Route::get('/procurement/projects/{project}/documents/{document}/pdf', [PublicProcurementController::class, 'streamDocumentPdf'])->name('public.procurement.document.pdf');
Route::get('/procurement/projects/{project}', [PublicProcurementController::class, 'show'])->name('public.procurement.show');

// Public certificate access by token only (secure)
Route::get('/certificate/view/{token}', [CertificateController::class, 'view'])->name('certificate.view');
Route::get('/certificate/{token}', [CertificateController::class, 'view'])->name('public.certificate.view');
Route::get('/qr/{token}.svg', [PublicAwardController::class, 'qrByToken'])->name('public.qr.show');

Route::get('/awards', [PublicAwardController::class, 'index'])->name('public.awards');


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
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
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
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/projects', [AdminController::class, 'projects'])->name('admin.projects');
    Route::get('/admin/projects/create', [AdminController::class, 'createProject'])->name('admin.projects.create');
    Route::post('/admin/projects', [AdminController::class, 'storeProject'])->name('admin.projects.store');
    Route::post('/admin/projects/wizard/store', [AdminController::class, 'storeProjectWizard'])->name('admin.projects.wizard.store');
    Route::get('/admin/projects/{project}/files', [AdminController::class, 'projectFiles'])->name('admin.project.files');
    Route::delete('/admin/projects/{project}/documents/{document}', [AdminController::class, 'destroyProjectDocument'])->name('admin.project.document.destroy');
    Route::get('/admin/projects/{project}', [AdminController::class, 'viewProject'])->name('admin.project.view');
    Route::get('/admin/projects/{project}/documents/{document}/pdf', [AdminController::class, 'streamProjectDocumentPdf'])->name('admin.project.document.pdf');
    Route::get('/admin/projects/{project}/edit', [AdminController::class, 'editProject'])->name('admin.project.edit');
    Route::match(['put', 'post'], '/admin/projects/{project}', [AdminController::class, 'updateProject'])->name('admin.project.update');
    Route::delete('/admin/projects/{project}', [AdminController::class, 'destroyProject'])->name('admin.project.destroy');
    Route::post('/admin/projects/{project}/publish', [AdminController::class, 'publishProject'])->name('admin.project.publish');
    Route::get('/admin/bidders', [AdminController::class, 'allBids'])->name('admin.bids');
    Route::get('/admin/bids/{bid}', [AdminController::class, 'viewBid'])->name('admin.bid.view');
    Route::get('/admin/bids/{bid}/documents/{document}/pdf', [AdminController::class, 'streamBidDocumentPdf'])->name('admin.bid.document.pdf');
    Route::get('/admin/bids/{bid}/documents/{document}', [AdminController::class, 'previewBidDocument'])->name('admin.bid.document.preview');
    Route::get('/admin/bids/{bid}/edit', [AdminController::class, 'editBid'])->name('admin.bid.edit');
    Route::put('/admin/bids/{bid}', [AdminController::class, 'updateBid'])->name('admin.bid.update');
    Route::patch('/admin/bids/{bid}/approve', [AdminController::class, 'approveBid'])->name('admin.bid.approve');
    Route::patch('/admin/bids/{bid}/reject', [AdminController::class, 'rejectBid'])->name('admin.bid.reject');
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/admin/users/{user}/review', [AdminController::class, 'reviewUser'])->name('admin.users.review');
    Route::get('/admin/users/{user}/documents/{document}', [AdminController::class, 'previewBidderDocument'])->name('admin.user.document.preview');
    Route::get('/admin/users/{user}/documents/{document}/pdf', [AdminController::class, 'streamBidderDocumentPdf'])->name('admin.user.document.pdf');
    Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::patch('/admin/users/{user}/approve', [AdminController::class, 'approveUser'])->name('admin.users.approve');
    Route::patch('/admin/users/{user}/reject', [AdminController::class, 'rejectUser'])->name('admin.users.reject');
    Route::delete('/admin/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
    Route::get('/admin/assignments', [AdminController::class, 'assignments'])->name('admin.assignments');
    Route::post('/admin/assignments', [AdminController::class, 'storeAssignment'])->name('admin.assignments.store');
    Route::delete('/admin/assignments/{assignment}', [AdminController::class, 'destroyAssignment'])->name('admin.assignments.destroy');
    Route::get('/admin/reports', [AdminController::class, 'reports'])->name('admin.reports');
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

    Route::get('/admin/awards', [AdminController::class, 'awards'])->name('admin.awards.index');
    Route::get('/admin/awards/{award}', [AdminController::class, 'viewAward'])->name('admin.award.view');
    Route::get('/admin/projects/{project}/award', [AdminController::class, 'createAward'])->name('admin.project.award');
    Route::post('/admin/awards/declare/{project}', [AdminController::class, 'declareWinner'])->name('admin.awards.declare');
    Route::post('/admin/awards', [AdminController::class, 'storeAward'])->name('admin.awards.store');
    Route::post('/admin/awards/{award}/certificate/upload', [AdminController::class, 'uploadCertificate'])->name('admin.awards.certificate.upload');
    Route::post('/admin/awards/{award}/certificate/replace', [AdminController::class, 'replaceCertificate'])->name('admin.awards.certificate.replace');
    Route::post('/admin/awards/{award}/revoke', [AdminController::class, 'revokeCertificate'])->name('admin.awards.revoke');
    Route::post('/admin/awards/{award}/regenerate-token', [AdminController::class, 'regenerateQrToken'])->name('admin.awards.regenerate.token');

    // Procurement management
    Route::get('/procurements', [ProcurementController::class, 'index'])->name('procurements.index');
    Route::get('/procurements/publish', [ProcurementController::class, 'publish'])->name('procurements.publish');
});

Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('/staff/dashboard', [StaffController::class, 'index'])->name('staff.dashboard');
    Route::get('/staff/assign-projects', [StaffController::class, 'assignProjects'])->name('staff.assign-projects');
    Route::patch('/staff/projects/{project}/status', [StaffController::class, 'updateProjectStatus'])->name('staff.projects.status');
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
});

Route::middleware(['auth', 'approved.bidder'])->group(function () {
    Route::get('/bidder/dashboard', [BidderController::class, 'index'])->name('bidder.dashboard');
    Route::get('/bidder/available-projects', [BidderController::class, 'availableProjects'])->name('bidder.available-projects');
    Route::get('/bidder/projects/{project}/documents/{document}', [BidderController::class, 'previewProjectDocument'])->name('bidder.project.document.preview');
    Route::get('/bidder/projects/{project}/documents/{document}/pdf', [BidderController::class, 'streamProjectDocumentPdf'])->name('bidder.project.document.pdf');
    Route::get('/bidder/my-bids', [BidderController::class, 'myBids'])->name('bidder.my-bids');
    Route::get('/bidding-track', [BiddingTrackController::class, 'index'])->name('bidder.bidding-track');
    Route::get('/bidding-track/data', [BiddingTrackController::class, 'data'])->name('bidder.bidding-track.data');
    Route::get('/bidder/awarded-contracts', [BidderController::class, 'awardedContracts'])->name('bidder.awarded-contracts');
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
    Route::post('/bidder/projects/{project}/bids', [BidderController::class, 'submitBid'])->name('bidder.bids.store');
});



// procurementController


// ACCOUNT



//admin route
