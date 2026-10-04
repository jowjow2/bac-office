<?php

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BiddingFeePayment;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\BiddingClosedNotices;
use App\Support\LiveVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Pages stay live without a socket server: the polled notification feed
 * returns a fingerprint of what the page shows, and passing deadlines are
 * announced to the bidders and the BAC.
 */
beforeEach(function () {
    testCase()->withoutVite();
    $user = fn (string $email, string $role, array $extra = []) => User::create(array_merge([
        'name' => ucfirst($role).' User', 'email' => $email, 'password' => Hash::make('password'), 'role' => $role, 'status' => 'active',
    ], $extra));
    $this->admin = $user('live-admin@example.com', 'admin');
    $this->staff = $user('live-staff@example.com', 'staff');
    $this->bidder = $user('live-bidder@example.com', 'bidder', ['company' => 'Mindoro Builders']);
    $this->bidder->bidderProfile()->create(['company_name' => 'Mindoro Builders', 'contact_person' => 'Juan', 'contact_number' => '09171234567', 'business_address' => 'San Jose', 'approval_status' => 'approved']);
    $this->payer = $user('live-payer@example.com', 'bidder', ['company' => 'Ana Trading']);

    $this->project = Project::create([
        'title' => 'Supply of Office Equipment', 'description' => 'Office equipment.', 'reference_no' => 'SJOM-2026-G-090',
        'category' => 'goods', 'procurement_mode' => 'public_bidding', 'budget' => 1500000, 'status' => 'open',
        'deadline' => now()->addMinutes(30), 'philgeps_reference_no' => '55667788',
    ]);
    ProjectSchedule::create(['project_id' => $this->project->id, 'date_posted' => now()->subDays(8)->toDateString(), 'bid_submission_deadline' => $this->project->deadline, 'bid_opening_date' => $this->project->deadline->copy()->addHour()]);
});

it('changes the page fingerprint when a record changes or a deadline passes', function () {
    $version = fn (User $user, string $scope) => LiveVersion::for($scope, $user);
    $before = $version($this->admin, 'bids');

    Bid::create(['project_id' => $this->project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 1400000, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now(), 'receipt_no' => 'R-1']);
    $afterBid = $version($this->admin, 'bids');
    expect($afterBid)->not->toBe($before);

    // A bidder's page follows only their own bids.
    $payerBefore = $version($this->payer, 'bidder-bids');
    Bid::create(['project_id' => $this->project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 1, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC]);
    expect($version($this->payer, 'bidder-bids'))->toBe($payerBefore);

    // Nothing is edited, but the deadline passes.
    $beforeDeadline = $version($this->admin, 'bids');
    $this->travel(31)->minutes();
    expect($version($this->admin, 'bids'))->not->toBe($beforeDeadline);

    // The feed returns it for a known page only.
    testCase()->actingAs($this->admin)->getJson(route('notifications.feed', ['live' => 'bids']))->assertOk()->assertJsonPath('live_version', $version($this->admin, 'bids'));
    testCase()->actingAs($this->admin)->getJson(route('notifications.feed'))->assertOk()->assertJsonPath('live_version', null);
    expect(LiveVersion::scopeForRoute('bidder.available-projects'))->toBe('bidder-projects')
        ->and(LiveVersion::scopeForRoute('admin.reports'))->toBeNull();
});

it('announces a passed deadline once to the bidders and the BAC', function () {
    Assignment::create(['project_id' => $this->project->id, 'staff_id' => $this->staff->id]);
    Bid::create(['project_id' => $this->project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 1400000, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now(), 'receipt_no' => 'R-1']);
    BiddingFeePayment::create(['project_id' => $this->project->id, 'user_id' => $this->payer->id, 'amount' => 5000, 'or_number' => 'OR-9', 'status' => BiddingFeePayment::STATUS_VERIFIED, 'paid_at' => now()->toDateString(), 'verified_at' => now(), 'recorded_by' => $this->staff->id]);

    // Still open: nothing to announce.
    BiddingClosedNotices::sweep();
    expect(UserNotification::where('title', 'Bidding closed')->count())->toBe(0);

    $this->travel(31)->minutes();
    Cache::flush();
    testCase()->actingAs($this->payer)->getJson(route('notifications.feed'))->assertOk();

    $note = fn (User $user) => UserNotification::where('user_id', $user->id)->where('title', 'Bidding closed')->first();
    expect($note($this->bidder)->message)->toContain('Your bid is on record')
        ->and($note($this->payer)->message)->toContain('No official bid from you')
        ->and($note($this->admin)->message)->toContain('1 bid received')
        ->and($note($this->staff))->not->toBeNull()
        ->and(AuditLog::where('action', BiddingClosedNotices::ACTION)->count())->toBe(1);

    // Once only.
    Cache::flush();
    BiddingClosedNotices::sweep();
    expect(UserNotification::where('title', 'Bidding closed')->count())->toBe(4);
});

it('marks each open project row with the second its bidding closes', function () {
    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))->assertOk()
        ->assertSee('data-live-deadline="'.$this->project->deadline->getTimestampMs().'"', false)
        ->assertSee('data-live-close', false)
        ->assertSee('liveScope: "bidder-projects"', false);
});
