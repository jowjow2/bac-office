<?php

use App\Models\Bid;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidRanking;
use App\Support\BidWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The Abstract-of-bids ranking follows the project's award criterion and only
 * ever uses components whose opening was recorded.
 */
beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'rank-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);

    $this->project = function (array $attributes = []): Project {
        $project = Project::create($attributes + [
            'reference_no' => 'RANK-'.fake()->unique()->numerify('###'), 'title' => 'Supply of office equipment', 'description' => 'Ranking test.',
            'budget' => 1000000, 'status' => 'closed', 'deadline' => now()->subHours(3),
            'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009', 'award_criterion' => 'lowest_calculated_bid',
        ]);
        ProjectSchedule::create(['project_id' => $project->id, 'bid_submission_deadline' => now()->subHours(3), 'bid_opening_date' => now()->subHours(2)]);
        $project->forceFill(['bids_opened_at' => now()->subHour(), 'bids_opened_by' => $this->admin->id])->save();

        return $project->fresh();
    };

    // $financial: record the financial opening; $score: recorded technical score.
    $this->bid = function (Project $project, string $company, float $amount, bool $financial = true, ?float $score = null, int $minutesAgo = 200): Bid {
        $bidder = User::create(['name' => $company, 'email' => str($company)->slug().'@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => $company]);

        $bid = Bid::create([
            'project_id' => $project->id, 'user_id' => $bidder->id, 'bid_amount' => $amount, 'status' => 'pending',
            'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC,
            'submitted_at' => now()->subMinutes($minutesAgo), 'receipt_no' => 'R-'.$bidder->id,
        ]);
        $bid->forceFill([
            'financial_opened_at' => $financial ? now()->subMinutes(30) : null, 'financial_opened_by' => $financial ? $this->admin->id : null,
            'documents_validated_at' => now()->subMinutes(50), 'documents_validated_by' => $this->admin->id,
            'technical_score' => $score, 'technical_scored_at' => $score !== null ? now()->subMinutes(40) : null,
        ])->save();

        return $bid->fresh();
    };

    $this->rank = fn (Project $project) => app(BidRanking::class)->forProjects([$project->id]);
});

it('LCRB: ranks opened bids from lowest price, leaving out sealed, above-ABC and disqualified bids', function () {
    $project = ($this->project)();
    $second = ($this->bid)($project, 'Mindoro Builders', 950000);
    $first = ($this->bid)($project, 'Sablayan Trading', 910000);
    $third = ($this->bid)($project, 'Occidental Supply', 990000);
    $sealed = ($this->bid)($project, 'Sealed Corp', 800000, financial: false);
    $above = ($this->bid)($project, 'Over Budget Inc', 1000000.01);
    $out = ($this->bid)($project, 'Failed Postqual Co', 700000);
    $out->forceFill(['disqualified_at' => now()])->save();

    $ranks = ($this->rank)($project);

    expect($ranks[$first->id])->toMatchArray(['status' => BidRanking::RANKED, 'rank' => 1, 'of' => 3, 'label' => 'Lowest calculated bid', 'provisional' => true])
        ->and($ranks[$second->id]['rank'])->toBe(2)
        ->and($ranks[$third->id]['rank'])->toBe(3)
        ->and($ranks[$sealed->id]['status'])->toBe(BidRanking::SEALED)
        ->and($ranks[$sealed->id]['rank'])->toBeNull()
        ->and($ranks[$above->id]['status'])->toBe(BidRanking::ABOVE_ABC)
        ->and($ranks[$out->id]['status'])->toBe(BidRanking::DISQUALIFIED);
});

it('requires the highest-ranked eligible bidder to complete post-qualification before the next bidder', function () {
    $project = ($this->project)();
    $first = ($this->bid)($project, 'Lowest Bidder', 700000);
    $second = ($this->bid)($project, 'Next Bidder', 800000);
    $first->forceFill(['evaluated_at' => now()])->save();
    $second->forceFill(['evaluated_at' => now()])->save();

    $workflow = app(BidWorkflow::class);
    expect($workflow->guardError($second->fresh(['project']), BidWorkflow::START_POST_QUALIFICATION, $this->admin))
        ->toContain('highest-ranked eligible bidder');

    $first->forceFill([
        'post_qualification_at' => now()->subMinute(),
        'post_qualification_result' => Bid::POST_QUALIFICATION_FAILED,
        'post_qualification_completed_at' => now(),
        'disqualified_at' => now(),
    ])->save();

    expect($workflow->guardError($second->fresh(['project']), BidWorkflow::START_POST_QUALIFICATION, $this->admin))->toBeNull();
});
it('LCRB: equal prices share a rank and are flagged as a tie', function () {
    $project = ($this->project)();
    $a = ($this->bid)($project, 'Alpha Builders', 900000, minutesAgo: 300);
    $b = ($this->bid)($project, 'Bravo Builders', 900000, minutesAgo: 200);
    $c = ($this->bid)($project, 'Charlie Builders', 950000);

    $ranks = ($this->rank)($project);

    expect([$ranks[$a->id]['rank'], $ranks[$b->id]['rank'], $ranks[$c->id]['rank']])->toBe([1, 1, 3])
        ->and($ranks[$a->id]['tied'])->toBeTrue()
        ->and($ranks[$c->id]['tied'])->toBeFalse()
        ->and($ranks[$a->id]['provisional'])->toBeFalse();
});

it('MEARB: ranks by the combined quality-price rating and skips bids below the minimum score', function () {
    $project = ($this->project)(['award_criterion' => 'mearb', 'quality_price_ratio' => 60, 'minimum_technical_score' => 70]);
    // A: 0.6*90 + 0.4*(800k/900k*100) = 54 + 35.56 = 89.56
    $a = ($this->bid)($project, 'Quality First', 900000, score: 90);
    // B: 0.6*75 + 0.4*100 = 45 + 40 = 85
    $b = ($this->bid)($project, 'Cheapest Co', 800000, score: 75);
    $low = ($this->bid)($project, 'Low Score Inc', 700000, financial: false, score: 60);

    $ranks = ($this->rank)($project);

    expect($ranks[$a->id]['rank'])->toBe(1)
        ->and($ranks[$a->id]['score'])->toBe(89.5556)
        ->and($ranks[$b->id]['rank'])->toBe(2)
        ->and($ranks[$low->id]['status'])->toBe(BidRanking::BELOW_MINIMUM);

    // Without the quality-price ratio, MEARB bids cannot be rated yet.
    $project->forceFill(['quality_price_ratio' => null])->save();
    expect(($this->rank)($project->fresh())[$a->id]['status'])->toBe(BidRanking::PENDING);
});

it('MARB: ranks by technical score without needing any financial opening', function () {
    $project = ($this->project)(['award_criterion' => 'marb']);
    $a = ($this->bid)($project, 'Top Rated', 990000, financial: false, score: 92);
    $b = ($this->bid)($project, 'Runner Up', 800000, financial: false, score: 88);
    $unscored = ($this->bid)($project, 'Not Scored', 850000, financial: false);

    $ranks = ($this->rank)($project);

    expect($ranks[$a->id]['rank'])->toBe(1)
        ->and($ranks[$b->id]['rank'])->toBe(2)
        ->and($ranks[$unscored->id]['status'])->toBe(BidRanking::PENDING);
});

it('does not rank anything before the technical opening is recorded', function () {
    $project = ($this->project)();
    $project->forceFill(['bids_opened_at' => null, 'bids_opened_by' => null])->save();
    $bid = ($this->bid)($project->fresh(), 'Early Bird', 900000);

    expect(($this->rank)($project)[$bid->id]['status'])->toBe(BidRanking::SEALED);
});

it('shows the ranking in the bid table, the project panel and the review modal without revealing sealed prices', function () {
    $project = ($this->project)(['reference_no' => 'RANK-UI']);
    $first = ($this->bid)($project, 'Sablayan Trading', 910000);
    ($this->bid)($project, 'Mindoro Builders', 950000);
    ($this->bid)($project, 'Sealed Corp', 812345.67, financial: false);

    testCase()->actingAs($this->admin)->get(route('admin.bids'))->assertOk()
        ->assertSee('Lowest calculated bid')->assertSee('of 2')
        ->assertDontSee('Abstract of bids (LCRB)')
        ->assertDontSee('812,345.67');

    testCase()->actingAs($this->admin)->get(route('admin.bids', ['project' => $project->id]))->assertOk()
        ->assertSee('Abstract of bids (LCRB)')
        ->assertSee('Provisional')
        ->assertSee('Not ranked: 1 still sealed.')
        ->assertSeeInOrder(['Sablayan Trading', 'Mindoro Builders'])
        ->assertDontSee('812,345.67');

    testCase()->actingAs($this->admin)->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('admin.bid.view', $first))->assertOk()
        ->assertSee('Lowest calculated bid');
});
