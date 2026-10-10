<?php

use App\Models\Bid;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    // Wednesday 3:00 PM, Manila.
    $this->travelTo(Carbon::parse('2026-10-14 15:00', 'Asia/Manila'));
    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'reports-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->bidder = User::create(['name' => 'Mindoro Builders', 'email' => 'reports-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Builders']);

    $this->project = function (string $ref, string $deadline, string $opening): Project {
        $project = Project::create([
            'reference_no' => $ref, 'title' => "Project {$ref}", 'description' => 'Reports test.', 'category' => 'infrastructure',
            'budget' => 900000, 'status' => 'open', 'deadline' => Carbon::parse($deadline, 'Asia/Manila'),
            'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009', 'published_at' => Carbon::parse('2026-10-01 09:00', 'Asia/Manila'),
        ]);
        ProjectSchedule::create(['project_id' => $project->id, 'bid_submission_deadline' => Carbon::parse($deadline, 'Asia/Manila'), 'bid_opening_date' => Carbon::parse($opening, 'Asia/Manila')]);

        return $project;
    };
});

it('shows the pipeline, what needs action and the next 14 days from the saved schedule', function () {
    // Deadline passed at 2 PM, opening at 4 PM: closed, awaiting opening.
    $closed = ($this->project)('CLOSED', '2026-10-14 14:00', '2026-10-14 16:00');
    $open = ($this->project)('OPEN', '2026-10-20 10:00', '2026-10-20 10:30');
    Bid::create(['project_id' => $closed->id, 'user_id' => $this->bidder->id, 'bid_amount' => 850000, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subDay(), 'receipt_no' => 'R-1']);
    // A draft is not a bid.
    Bid::create(['project_id' => $open->id, 'user_id' => $this->bidder->id, 'bid_amount' => 800000, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC]);

    $page = testCase()->actingAs($this->admin)->get(route('admin.reports'))->assertOk();

    $page->assertSee('Procurement pipeline')
        ->assertSee('Needs action')
        ->assertSee('Submission closed · awaiting opening')
        ->assertSee('Project CLOSED')
        ->assertSee('Next 14 days')
        ->assertSee('Deadline for submission of bids')
        ->assertSee('This quarter')
        ->assertDontSee('Overdue');

    $kpis = collect($page->viewData('summaryCards'))->keyBy('label');
    expect($kpis['Official bids']['value'])->toBe(1)
        ->and($kpis['Accepting bids now']['value'])->toBe(1)
        ->and($kpis['Bidders per project']['value'])->toEqual(1.0)
        ->and($kpis['Savings vs ABC']['display'])->toBe('—');

    $stages = collect($page->viewData('pipeline')['stages'])->keyBy('key');
    expect($stages['published']['reached'])->toBe(2)
        ->and($stages['closed']['reached'])->toBe(1)
        ->and($stages['closed']['here'])->toBe(1)
        ->and($stages['opened']['reached'])->toBe(0);

    // Twelve months ending this month (Oct 31 minus 11 months must not overflow into December).
    $this->travelTo(Carbon::parse('2026-10-31 15:00', 'Asia/Manila'));
    $months = testCase()->actingAs($this->admin)->get(route('admin.reports'))->viewData('monthlyActivity');
    expect($months)->toHaveCount(12)->and($months[0]['key'])->toBe('2025-11')->and($months[11]['key'])->toBe('2026-10');

    $phases = collect($page->viewData('procurementStatusDistribution'))->pluck('value', 'key');
    expect($phases['accepting'])->toBe(1)->and($phases['awaiting_opening'])->toBe(1);
});

it('exports the same figures to CSV and PDF', function () {
    ($this->project)('CLOSED', '2026-10-14 14:00', '2026-10-14 16:00');

    $csv = testCase()->actingAs($this->admin)->get(route('admin.reports.export.csv'))->assertOk()->streamedContent();
    expect($csv)->toContain('Procurement Pipeline')->toContain('Needs Action')->toContain('Submission closed · awaiting opening');

    testCase()->actingAs($this->admin)->get(route('admin.reports.print'))->assertOk();
});

it('shows budget, fees, purchase requests, a per-mode breakdown and staff workload', function () {
    $admin = \App\Models\User::create(['name' => 'Rep Admin', 'email' => 'rep-extras@example.com', 'password' => \Illuminate\Support\Facades\Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    \App\Models\Project::create([
        'title' => 'Road Works Extra', 'description' => 'x', 'reference_no' => 'SJ-REP-1', 'category' => 'infrastructure',
        'procurement_mode' => 'public_bidding', 'budget' => 2500000, 'deadline' => now()->addDays(5), 'status' => 'open',
    ]);

    testCase()->withoutVite();
    testCase()->actingAs($admin)->get(route('admin.reports'))->assertOk()
        ->assertSee('Total ABC')->assertSee('₱2,500,000.00')
        ->assertSee('Bidding fees collected')->assertSee('Purchase requests waiting')
        ->assertSee('By procurement mode')->assertSee('Public Bidding')
        ->assertSee('Staff workload');

    testCase()->actingAs($admin)->get(route('admin.reports.export.csv'))->assertOk()
        ->assertDownload();
});
