<?php

use App\Models\AuditLog;
use App\Models\Award;
use App\Models\Bid;
use App\Models\BidTracking;
use App\Models\Project;
use App\Models\ProjectProceeding;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The procurement follows its saved schedule by server time (Asia/Manila):
 * a schedule change takes effect at once, a due bid opening happens without
 * a visit to the project, and dates never decide evaluation or award.
 */
beforeEach(function () {
    testCase()->withoutVite();
    // Wednesday 3:00 PM, Manila.
    $this->travelTo(Carbon::parse('2026-10-14 15:00', 'Asia/Manila'));

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'schedule-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->bidder = User::create(['name' => 'Mindoro Builders', 'email' => 'schedule-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Builders']);

    $this->makeProject = function (array $attributes = [], array $schedule = []): Project {
        $deadline = Carbon::parse('2026-10-20 10:00', 'Asia/Manila');
        $project = Project::create($attributes + [
            'reference_no' => 'SCHED-1', 'title' => 'Barangay road concreting', 'description' => 'Schedule test.',
            'category' => 'infrastructure', 'budget' => 900000, 'status' => 'open', 'deadline' => $deadline,
            'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009', 'submission_mode' => Project::SUBMISSION_ELECTRONIC,
            'award_criterion' => 'lowest_calculated_bid', 'published_at' => Carbon::parse('2026-10-01 09:00', 'Asia/Manila'),
        ]);
        ProjectSchedule::create($schedule + [
            'project_id' => $project->id, 'bid_submission_deadline' => $deadline, 'bid_opening_date' => $deadline->copy()->addMinutes(30),
        ]);

        return $project->fresh(['schedule']);
    };

    $this->makeBid = fn (Project $project): Bid => Bid::create([
        'project_id' => $project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 850000,
        'proposal_file' => 'bids/schedule-proposal.pdf', 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED,
        'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subDay(), 'receipt_no' => 'R-SCHED',
        'financial_opening_password_hash' => Hash::make('Opening-password-2026'),
    ]);

    $this->editSchedule = fn (Project $project, string $deadline, ?string $opening) => testCase()->actingAs($this->admin)
        ->from(route('admin.projects'))
        ->put(route('admin.project.update', $project), array_filter([
            'title' => $project->title, 'description' => $project->description, 'budget' => (string) $project->budget,
            'status' => 'open', 'deadline' => $deadline, 'bid_opening_date' => $opening,
        ], fn ($value) => $value !== null));
});

it('closes submissions and opens the bids at once when the new deadline and opening have passed', function () {
    $project = ($this->makeProject)();
    $bid = ($this->makeBid)($project);
    expect($project->isOpenForBidding())->toBeTrue();

    ($this->editSchedule)($project, '2026-10-14T10:00', '2026-10-14T10:30')->assertSessionHasNoErrors();

    $project = $project->fresh(['schedule']);
    expect($project->isOpenForBidding())->toBeFalse()
        ->and($project->status)->toBe('closed')
        ->and($project->bidsAreOpened())->toBeTrue()
        ->and($project->bids_opened_by)->toBeNull()
        ->and($project->bidOpeningBlocker())->toBeNull() // the BAC can still record that it conducted the opening
        ->and(AuditLog::where('action', 'project_schedule_changed')->count())->toBe(1)
        ->and(AuditLog::where('action', 'bid_technical_documents_auto_opened')->count())->toBe(1)
        ->and(BidTracking::where('bid_id', $bid->id)->where('status_title', 'Technical Components Opened')->exists())->toBeTrue()
        ->and(UserNotification::where('user_id', $this->bidder->id)->where('title', 'Schedule updated')->exists())->toBeTrue()
        ->and(UserNotification::where('user_id', $this->admin->id)->where('title', 'Bids opened as scheduled')->exists())->toBeTrue();

    // The opening reveals technical files only: the price and every decision stay with the BAC.
    $bid = $bid->fresh('project');
    expect($bid->isSealed())->toBeFalse()
        ->and($bid->isFinancialSealed())->toBeTrue()
        ->and($bid->status)->toBe('pending')
        ->and($bid->documents_validated_at)->toBeNull()
        ->and(Award::count())->toBe(0)
        ->and($project->isCompleted())->toBeFalse();

    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project))->assertSessionHasNoErrors();
    expect($project->fresh()->bids_opened_by)->toBe($this->admin->id);
    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project))->assertSessionHasErrors('bids_opened_at');
});

it('shows a passed deadline as closed before the opening and opens on the next request at the opening time', function () {
    $project = ($this->makeProject)();
    ($this->editSchedule)($project, '2026-10-14T14:30', '2026-10-14T15:30')->assertSessionHasNoErrors();

    $project = $project->fresh(['schedule']);
    expect($project->isOpenForBidding())->toBeFalse()
        ->and($project->bidsAreOpened())->toBeFalse()
        ->and($project->portalStatus()['label'])->toBe('Submission closed · awaiting opening');

    // Any page request after the opening time opens it, not only a visit to the project.
    $this->travelTo(Carbon::parse('2026-10-14 15:31', 'Asia/Manila'));
    testCase()->get('/')->assertOk();
    expect($project->fresh()->bidsAreOpened())->toBeTrue();
});

it('opens due projects from the cron endpoint, guarded by CRON_SECRET', function () {
    $due = ($this->makeProject)(['deadline' => Carbon::parse('2026-10-14 10:00', 'Asia/Manila')], ['bid_submission_deadline' => Carbon::parse('2026-10-14 10:00', 'Asia/Manila'), 'bid_opening_date' => Carbon::parse('2026-10-14 10:30', 'Asia/Manila')]);
    config()->set('services.cron.secret', 'cron-test-secret');

    // Without the secret nothing runs (the web middleware is skipped to see the endpoint alone).
    testCase()->withoutMiddleware(\App\Http\Middleware\ApplyProcurementClock::class)
        ->getJson(route('cron.procurement-schedule'))->assertUnauthorized();
    expect($due->fresh()->bidsAreOpened())->toBeFalse();

    testCase()->withoutMiddleware(\App\Http\Middleware\ApplyProcurementClock::class)
        ->withHeader('Authorization', 'Bearer cron-test-secret')
        ->getJson(route('cron.procurement-schedule'))->assertOk()->assertJson(['opened' => 1]);
    expect($due->fresh()->bidsAreOpened())->toBeTrue();
});

it('never opens drafts, archived projects or failed biddings by date', function () {
    $past = ['bid_submission_deadline' => Carbon::parse('2026-10-14 10:00', 'Asia/Manila'), 'bid_opening_date' => Carbon::parse('2026-10-14 10:30', 'Asia/Manila')];
    $draft = ($this->makeProject)(['reference_no' => 'D', 'status' => 'draft', 'published_at' => null, 'deadline' => $past['bid_submission_deadline']], $past);
    $archived = ($this->makeProject)(['reference_no' => 'A', 'archived_at' => now()->subHour(), 'deadline' => $past['bid_submission_deadline']], $past);
    $failed = ($this->makeProject)(['reference_no' => 'F', 'failed_bidding_at' => now()->subHour(), 'deadline' => $past['bid_submission_deadline']], $past);

    expect(app(\App\Support\BidOpening::class)->openDueTechnicalProjects())->toBe(0);
    foreach ([$draft, $archived, $failed] as $project) {
        expect($project->fresh()->bids_opened_at)->toBeNull();
    }
});

it('does not let a schedule change reopen a closed submission or rewrite an opened bidding', function () {
    // Deadline passed this morning, opening later today.
    $project = ($this->makeProject)(['deadline' => Carbon::parse('2026-10-14 10:00', 'Asia/Manila')], ['bid_submission_deadline' => Carbon::parse('2026-10-14 10:00', 'Asia/Manila'), 'bid_opening_date' => Carbon::parse('2026-10-14 16:00', 'Asia/Manila')]);
    ($this->editSchedule)($project, '2026-10-21T10:00', '2026-10-21T10:30')->assertSessionHasErrors('deadline');
    expect($project->fresh()->bidSubmissionDeadline()->format('Y-m-d H:i'))->toBe('2026-10-14 10:00');

    // Once opened, the dates are history.
    $this->travelTo(Carbon::parse('2026-10-14 16:01', 'Asia/Manila'));
    app(\App\Support\BidOpening::class)->openDueTechnicalProjects();
    ($this->editSchedule)($project->fresh(), '2026-10-14T10:00', '2026-10-15T10:30')->assertSessionHasErrors('bid_opening_date');
    expect($project->fresh()->schedule->bid_opening_date->format('Y-m-d H:i'))->toBe('2026-10-14 16:00');
});

it('rejects conflicting schedules and keeps a held pre-bid conference', function () {
    $project = ($this->makeProject)([], ['pre_bid_conference_date' => Carbon::parse('2026-10-05 09:00', 'Asia/Manila')]);

    // Opening before the deadline, and outside office hours.
    ($this->editSchedule)($project, '2026-10-22T10:00', '2026-10-22T09:00')->assertSessionHasErrors('bid_opening_date');
    ($this->editSchedule)($project, '2026-10-24T10:00', '2026-10-24T10:30')->assertSessionHasErrors('deadline'); // a Saturday

    ProjectProceeding::create(['project_id' => $project->id, 'type' => ProjectProceeding::TYPE_PRE_BID, 'title' => 'Pre-bid conference', 'occurred_at' => Carbon::parse('2026-10-05 09:00', 'Asia/Manila'), 'recorded_by' => $this->admin->id]);
    testCase()->actingAs($this->admin)->from(route('admin.projects'))->put(route('admin.project.update', $project), [
        'title' => $project->title, 'description' => $project->description, 'budget' => (string) $project->budget,
        'status' => 'open', 'deadline' => '2026-10-20T10:00', 'pre_bid_conference_date' => '2026-10-06T09:00',
    ])->assertSessionHasErrors('pre_bid_conference_date');

    expect($project->fresh(['schedule'])->schedule->bid_opening_date->format('Y-m-d H:i'))->toBe('2026-10-20 10:30');
});
