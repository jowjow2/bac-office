<?php

use App\Models\Bidder;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\ProjectPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Publishing a procurement tells the approved bidders, with a link to it.
 */
beforeEach(function () {
    testCase()->withoutVite();

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'np-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $make = function (string $email, string $company, string $approval, string $status) {
        $user = User::create(['name' => $company.' Rep', 'email' => $email, 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => $status, 'company' => $company]);
        Bidder::create(['user_id' => $user->id, 'company_name' => $company, 'contact_person' => 'Rep', 'contact_number' => '09170000000', 'business_address' => 'San Jose', 'approval_status' => $approval, 'approved_at' => $approval === 'approved' ? now() : null]);

        return $user;
    };
    $this->approved = $make('np-approved@example.com', 'Approved Builders', 'approved', 'active');
    $this->pending = $make('np-pending@example.com', 'Pending Builders', 'pending', 'pending');

    $this->project = Project::create([
        'title' => 'Supply of office chairs', 'description' => 'Chairs', 'budget' => 35000,
        'deadline' => now()->addWeek(), 'status' => 'draft', 'category' => 'goods',
    ]);
});

it('notifies approved bidders when a procurement is published, and no one else', function () {
    app(ProjectPublication::class)->publish($this->project, $this->admin);

    $notice = UserNotification::where('user_id', $this->approved->id)->where('type', 'project_available')->firstOrFail();
    expect($notice->title)->toBe('New procurement open for bidding')
        ->and($notice->message)->toContain('Supply of office chairs')
        ->and($notice->message)->toContain('ABC ₱35,000.00')
        ->and($notice->data['url'])->toEndWith('/bidder/opportunities/'.$this->project->id);

    expect(UserNotification::where('user_id', $this->pending->id)->where('type', 'project_available')->count())->toBe(0)
        ->and(UserNotification::where('user_id', $this->admin->id)->where('type', 'project_available')->count())->toBe(0);

    // The bidder sees it in the notification center.
    testCase()->actingAs($this->approved)->get(route('bidder.notifications'))->assertOk()->assertSee('New procurement open for bidding');
});

it('tells bidders only once, however many times publish is called', function () {
    $publication = app(ProjectPublication::class);
    $publication->publish($this->project, $this->admin);
    $publication->publish($this->project->fresh(), $this->admin);

    expect(UserNotification::where('user_id', $this->approved->id)->where('type', 'project_available')->count())->toBe(1);
});

it('announces a scheduled publication when its time arrives, once', function () {
    app(ProjectPublication::class)->publish($this->project, $this->admin, now()->addHour());

    // Scheduled for later: nothing is announced yet, so no one is sent to a page they cannot open.
    expect(UserNotification::where('user_id', $this->approved->id)->where('type', 'project_available')->count())->toBe(0);

    $this->travel(61)->minutes();
    testCase()->actingAs($this->approved)->getJson(route('notifications.feed'))->assertOk();
    expect(UserNotification::where('user_id', $this->approved->id)->where('type', 'project_available')->count())->toBe(1);

    // Every later poll leaves it at one.
    $this->travel(30)->seconds();
    testCase()->actingAs($this->approved)->getJson(route('notifications.feed'))->assertOk();
    expect(UserNotification::where('user_id', $this->approved->id)->where('type', 'project_available')->count())->toBe(1);
});

it('tells a bidder approved later which procurements are open, once, and says nothing when none are', function () {
    $late = $this->pending;
    $publication = app(ProjectPublication::class);

    // Nothing is open: no empty notice.
    $publication->notifyApprovedBidderOfOpenProjects($late);
    expect(UserNotification::where('user_id', $late->id)->where('type', 'project_available')->count())->toBe(0);

    // Published before this bidder was approved, so the publication notice never reached them.
    $publication->publish($this->project, $this->admin);
    expect(UserNotification::where('user_id', $late->id)->where('type', 'project_available')->count())->toBe(0);

    $publication->notifyApprovedBidderOfOpenProjects($late);
    $notice = UserNotification::where('user_id', $late->id)->where('type', 'project_available')->firstOrFail();
    expect($notice->title)->toBe('A procurement is open for bidding')
        ->and($notice->message)->toContain('Supply of office chairs')
        ->and($notice->data['url'])->toEndWith('/bidder/opportunities/'.$this->project->id);

    $second = Project::create(['title' => 'Supply of laptops', 'description' => 'Laptops', 'budget' => 90000, 'deadline' => now()->addWeek(), 'status' => 'draft', 'category' => 'goods']);
    $publication->publish($second, $this->admin);
    $publication->notifyApprovedBidderOfOpenProjects($late);
    $latest = UserNotification::where('user_id', $late->id)->where('title', 'like', '%procurements are open%')->firstOrFail();
    expect($latest->message)->toContain('Supply of laptops')->and($latest->data['url'])->toEndWith('/bidder/available-projects');
});
