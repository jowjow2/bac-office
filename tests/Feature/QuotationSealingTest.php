<?php

use App\Models\Bid;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidRanking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Small Value Procurement has no bid-opening ceremony, but quotations are
 * reviewed together after the deadline: before it the BAC sees no price and
 * no ranking.
 */
it('keeps quotation prices and the ranking hidden until the quotation deadline', function () {
    testCase()->withoutVite();
    $admin = User::create(['name' => 'BAC Admin', 'email' => 'q-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $project = Project::create([
        'title' => 'Purchase of Office Supplies', 'description' => 'Supplies.', 'reference_no' => 'SJ-BAC-2026-G-001', 'category' => 'goods',
        'procurement_mode' => 'small_value_procurement', 'legal_basis' => 'ra_12009', 'budget' => 24450, 'status' => 'open', 'deadline' => now()->addHours(5),
    ]);
    ProjectSchedule::create(['project_id' => $project->id, 'date_posted' => now()->subDays(4)->toDateString(), 'bid_submission_deadline' => $project->deadline]);

    $bids = collect([['Divine Company', 20000], ['PCIC Corporation', 21000]])->map(function ($row, $i) use ($project) {
        $bidder = User::create(['name' => $row[0], 'email' => "q-bidder{$i}@example.com", 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => $row[0]]);

        return Bid::create(['project_id' => $project->id, 'user_id' => $bidder->id, 'bid_amount' => $row[1], 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now(), 'receipt_no' => 'R-'.$i]);
    });

    $rank = fn () => app(BidRanking::class)->forProjects([$project->id]);
    expect(collect($rank())->pluck('status')->unique()->all())->toBe([BidRanking::SEALED])
        ->and($bids->first()->fresh()->isFinancialSealed())->toBeTrue();
    testCase()->actingAs($admin)->get(route('admin.bids'))->assertOk()
        ->assertDontSee('20,000.00')->assertDontSee('21,000.00')
        ->assertSee('Until the deadline for submission of quotations');

    // The deadline passes: the quotations are reviewed together and ranked by price.
    $this->travel(6)->hours();
    expect($rank()[$bids->first()->id]['rank'])->toBe(1)
        ->and($rank()[$bids->last()->id]['rank'])->toBe(2);
    testCase()->actingAs($admin)->get(route('admin.bids'))->assertOk()
        ->assertSee('20,000.00')->assertSee('21,000.00');
});
