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
