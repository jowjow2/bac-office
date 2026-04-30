<?php

use App\Models\Award;
use App\Models\Project;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PublicProcurementController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\BidderController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    $publicProjectsCount = Project::query()->visibleToPublic()->count();
    $openProjectsCount = Project::query()->where('status', 'open')->count();
    $awardedContractsCount = Award::query()->count();
    $totalAwardedValue = (float) Award::query()->sum('contract_amount');

    $latestProjects = Project::query()
        ->visibleToPublic()
        ->withCount('bids')
        ->latest()
        ->take(3)
        ->get();

    $latestAwards = Award::query()
        ->with(['project:id,title', 'bid.user:id,name,company'])
        ->latest('contract_date')
        ->take(3)
        ->get();

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
Route::get('/procurement/projects/{project}', [PublicProcurementController::class, 'show'])->name('public.procurement.show');
Route::get('/procurement/projects/{project}/scan', [PublicProcurementController::class, 'scan'])->name('public.procurement.scan');

Route::get('/awards', function (Request $request) {
    $query = trim((string) $request->query('q', ''));

    $awards = Award::query()
        ->with(['project', 'bid.user'])
        ->when($query !== '', function ($builder) use ($query) {
            $builder->where(function ($nested) use ($query) {
                $nested->whereHas('project', function ($projectQuery) use ($query) {
                    $projectQuery->where('title', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })->orWhereHas('bid.user', function ($userQuery) use ($query) {
                    $userQuery->where('name', 'like', "%{$query}%")
                        ->orWhere('company', 'like', "%{$query}%");
                });
            });
        })
        ->latest('contract_date')
        ->get();

    return view('pages.awards', compact('awards', 'query'));
})->name('public.awards');


Route::get('/menu', function () {
    return view('menu');
});

Route::view('/slider', 'slider')->name('slider');

// Homepage 



Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});


Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/admin/projects', [AdminController::class, 'projects'])->name('admin.projects');
    Route::post('/admin/projects', [AdminController::class, 'storeProject'])->name('admin.projects.store');
    Route::get('/admin/projects/{project}', [AdminController::class, 'viewProject'])->name('admin.project.view');
    Route::get('/admin/projects/{project}/edit', [AdminController::class, 'editProject'])->name('admin.project.edit');
    Route::match(['put', 'post'], '/admin/projects/{project}', [AdminController::class, 'updateProject'])->name('admin.project.update');
    Route::delete('/admin/projects/{project}', [AdminController::class, 'destroyProject'])->name('admin.project.destroy');
    Route::get('/admin/bidders', [AdminController::class, 'allBids'])->name('admin.bids');
    Route::get('/admin/bids/{bid}', [AdminController::class, 'viewBid'])->name('admin.bid.view');
    Route::get('/admin/bids/{bid}/edit', [AdminController::class, 'editBid'])->name('admin.bid.edit');
    Route::put('/admin/bids/{bid}', [AdminController::class, 'updateBid'])->name('admin.bid.update');
    Route::patch('/admin/bids/{bid}/approve', [AdminController::class, 'approveBid'])->name('admin.bid.approve');
    Route::patch('/admin/bids/{bid}/reject', [AdminController::class, 'rejectBid'])->name('admin.bid.reject');
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
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

    Route::get('/admin/awards', [AdminController::class, 'awards'])->name('admin.awards');
    Route::get('/admin/awards/{award}', [AdminController::class, 'viewAward'])->name('admin.award.view');
    Route::get('/admin/projects/{project}/award', [AdminController::class, 'createAward'])->name('admin.project.award');
    Route::post('/admin/awards', [AdminController::class, 'storeAward'])->name('admin.awards.store');
});

Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('/staff/dashboard', [StaffController::class, 'index'])->name('staff.dashboard');
    Route::get('/staff/assign-projects', [StaffController::class, 'assignProjects'])->name('staff.assign-projects');
    Route::get('/staff/review-bids', [StaffController::class, 'reviewBids'])->name('staff.review-bids');
    Route::get('/staff/reports', [StaffController::class, 'reports'])->name('staff.reports');
    Route::get('/staff/reports/export/csv', [StaffController::class, 'exportReportsCsv'])->name('staff.reports.export.csv');
    Route::get('/staff/reports/export/print', [StaffController::class, 'printReports'])->name('staff.reports.print');
    Route::get('/staff/notifications', [StaffController::class, 'notifications'])->name('staff.notifications');
    Route::post('/staff/notifications/read-all', [StaffController::class, 'markAllNotificationsRead'])->name('staff.notifications.read-all');
    Route::get('/staff/bids/{bid}/proposal', [StaffController::class, 'downloadBidProposal'])->name('staff.bids.proposal.download');
    Route::patch('/staff/projects/{project}/status', [StaffController::class, 'updateProjectStatus'])->name('staff.projects.status');
    Route::patch('/staff/bids/{bid}/validate', [StaffController::class, 'validateBidDocuments'])->name('staff.bids.validate');
    Route::patch('/staff/bids/{bid}/reject', [StaffController::class, 'rejectBid'])->name('staff.bids.reject');
    Route::patch('/staff/bids/{bid}/recommend', [StaffController::class, 'recommendBid'])->name('staff.bids.recommend');
});

Route::middleware(['auth', 'bidder'])->group(function () {
    Route::get('/bidder/dashboard', [BidderController::class, 'index'])->name('bidder.dashboard');
    Route::get('/bidder/available-projects', [BidderController::class, 'availableProjects'])->name('bidder.available-projects');
    Route::get('/bidder/my-bids', [BidderController::class, 'myBids'])->name('bidder.my-bids');
    Route::get('/bidder/awarded-contracts', [BidderController::class, 'awardedContracts'])->name('bidder.awarded-contracts');
    Route::get('/bidder/company-profile', [BidderController::class, 'companyProfile'])->name('bidder.company-profile');
    Route::get('/bidder/notifications', [BidderController::class, 'notifications'])->name('bidder.notifications');
    Route::post('/bidder/notifications/read-all', [BidderController::class, 'markAllNotificationsRead'])->name('bidder.notifications.read-all');
    Route::patch('/bidder/profile', [BidderController::class, 'updateProfile'])->name('bidder.profile.update');
    Route::post('/bidder/company-profile/documents', [BidderController::class, 'uploadDocument'])->name('bidder.documents.store');
    Route::post('/bidder/projects/{project}/bids', [BidderController::class, 'submitBid'])->name('bidder.bids.store');
});



// procurementController


// ACCOUNT



//admin route


