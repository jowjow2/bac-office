<?php

use App\Models\AuditLog;
use App\Models\Award;
use App\Models\Bid;
use App\Models\BidTracking;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The HoPE's approval of the BAC-recommended bid hands exactly one award
 * record to Awards & Contracts; the Notice of Award, contract signing and NTP
 * then complete that same record. Nothing is picked by price alone.
 */
beforeEach(function () {
    testCase()->withoutVite();
    Storage::fake('local');

    $this->admin = User::create(['name' => 'HoPE Office', 'email' => 'handoff-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'BAC Secretariat', 'email' => 'handoff-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);

    $this->project = function (string $title): Project {
        $project = Project::create([
            'reference_no' => 'SJ-HO-'.fake()->unique()->numerify('####'), 'title' => $title, 'description' => 'Hand-off test.',
            'category' => 'goods', 'budget' => 1000000, 'status' => 'closed', 'deadline' => now()->subDays(3),
            'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009', 'award_criterion' => 'lowest_calculated_bid',
        ]);
        ProjectSchedule::create(['project_id' => $project->id, 'bid_submission_deadline' => now()->subDays(3), 'bid_opening_date' => now()->subDays(3)->addHour()]);
        $project->forceFill(['bids_opened_at' => now()->subDays(3)->addHour(), 'bids_opened_by' => $this->admin->id])->save();

        return $project->fresh();
    };

    // A submitted, opened bid; 'recommended' puts it where the BAC resolution left it.
    $this->bid = function (Project $project, string $company, float $amount, bool $recommended = false): Bid {
        $bidder = User::create(['name' => $company, 'email' => str($company)->slug().'@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => $company]);
        $bid = Bid::create([
            'project_id' => $project->id, 'user_id' => $bidder->id, 'bid_amount' => $amount, 'status' => 'pending',
            'workflow_step' => $recommended ? Bid::STEP_RECOMMENDED : Bid::STEP_EVALUATED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC,
            'submitted_at' => now()->subDays(4), 'receipt_no' => 'R-'.$bidder->id,
        ]);
        $bid->forceFill(array_filter([
            'documents_validated_at' => now()->subDays(2), 'documents_validated_by' => $this->admin->id, 'eligibility_status' => Bid::ELIGIBILITY_VALID,
            'financial_opened_at' => now()->subDays(2), 'financial_opened_by' => $this->admin->id,
            'bac_evaluation_at' => now()->subDays(2), 'evaluated_at' => now()->subDays(2),
            'post_qualification_at' => $recommended ? now()->subDay() : null,
            'post_qualification_result' => $recommended ? Bid::POST_QUALIFICATION_PASSED : null,
            'post_qualification_completed_at' => $recommended ? now()->subDay() : null,
            'bac_recommended_at' => $recommended ? now()->subHours(5) : null,
        ]))->save();

        return $bid->fresh();
    };

    $this->decide = fn (Bid $bid, string $action, ?User $as = null, array $extra = []) => testCase()
        ->actingAs($as ?? $this->admin)
        ->post(route('admin.bid.decision', $bid), ['action' => $action] + $extra);

    $this->pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
});

it('hands the HoPE-approved bid to Awards & Contracts as exactly one award, then completes it at the Notice of Award', function () {
    $project = ($this->project)('Supply of laboratory reagents');
    $lowest = ($this->bid)($project, 'Lowest Trading', 700000);
    $winner = ($this->bid)($project, 'Qualified Supplies Inc.', 820000, recommended: true);

    ($this->decide)($winner, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();

    $award = Award::sole();
    expect($award->project_id)->toBe($project->id)
        ->and($award->bid_id)->toBe($winner->id)
        ->and($award->bidder_id)->toBe($winner->user_id)
        ->and((float) $award->contract_amount)->toBe(820000.0)
        ->and($award->award_approved_by)->toBe($this->admin->id)
        ->and($award->award_approved_at)->not->toBeNull()
        ->and($award->awaitsNoticeOfAward())->toBeTrue()
        ->and($award->notice_of_award_date)->toBeNull()
        ->and(AuditLog::where('action', 'award_approved_handoff')->where('auditable_id', $award->id)->exists())->toBeTrue();

    // Admin list and the winning supplier see it at once, as "Award approved".
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()
        ->assertSee('Supply of laboratory reagents')
        ->assertSee('Qualified Supplies Inc.')
        ->assertSee('Award approved')
        ->assertSee('Notice of Award pending')
        ->assertSee('Lowest calculated bid');
    testCase()->actingAs($winner->user)->get(route('bidder.awarded-contracts'))->assertOk()
        ->assertSee('Supply of laboratory reagents')
        ->assertSee('Award approved')
        ->assertDontSee('For Delivery');

    // The lowest bidder sees its own outcome, not the contract.
    expect($lowest->fresh()->progress()->adminStatus()['key'])->toBe('not_awarded');
    testCase()->actingAs($lowest->user)->get(route('bidder.awarded-contracts'))->assertOk()
        ->assertDontSee('Supply of laboratory reagents');

    // Not public before the Notice of Award.
    testCase()->get(route('public.awards'))->assertDontSee('Supply of laboratory reagents');
    testCase()->get(route('certificate.verify', $award))->assertNotFound();

    // Retrying the hand-off and approving again create nothing new.
    app(BidWorkflow::class)->handOffApprovedAward($winner->fresh());
    ($this->decide)($winner->fresh(), BidWorkflow::APPROVE_AWARD)->assertSessionHasErrors('milestone');
    expect(Award::count())->toBe(1);

    // The Notice of Award completes the same record and posts it publicly.
    testCase()->actingAs($this->admin)->post(route('admin.awards.declare', $project), [
        'bid_id' => $winner->id,
        'certificate_file' => ($this->pdf)('noa.pdf'),
    ])->assertSessionHasNoErrors();

    $award->refresh();
    expect(Award::count())->toBe(1)
        ->and($award->notice_of_award_date->isToday())->toBeTrue()
        ->and($award->certificate_status)->toBe(Award::STATUS_VALID)
        ->and($award->award_approved_by)->toBe($this->admin->id);
    testCase()->get(route('public.awards'))->assertSee('Supply of laboratory reagents');
});

it('creates no award for a BAC recommendation that is not approved, a disapproval, or the lowest price alone', function () {
    $project = ($this->project)('Supply of office chairs');
    $lowest = ($this->bid)($project, 'Cheapest Chairs', 50000);
    $recommended = ($this->bid)($project, 'Recommended Chairs', 60000, recommended: true);

    // Opening the pages does not pick anyone.
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()->assertDontSee('Cheapest Chairs');
    testCase()->actingAs($lowest->user)->get(route('bidder.awarded-contracts'))->assertOk();
    expect(Award::count())->toBe(0);

    // Only the HoPE (admin) may approve; the BAC Secretariat cannot.
    ($this->decide)($recommended, BidWorkflow::APPROVE_AWARD, $this->staff)->assertForbidden();
    expect(fn () => app(BidWorkflow::class)->apply($recommended, BidWorkflow::APPROVE_AWARD, $this->staff))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    // The lowest bid was never recommended, so it cannot be approved or handed off.
    ($this->decide)($lowest, BidWorkflow::APPROVE_AWARD)->assertSessionHasErrors('milestone');
    expect(fn () => app(BidWorkflow::class)->handOffApprovedAward($lowest))->toThrow(Illuminate\Validation\ValidationException::class);

    // A disapproval is not an award.
    ($this->decide)($recommended, BidWorkflow::DISAPPROVE_AWARD, null, ['reason' => 'Resolution lacks the post-qualification report.'])->assertSessionHasNoErrors();
    expect(Award::count())->toBe(0);
});

it('keeps each project to its own award', function () {
    $first = ($this->project)('Supply of printers');
    $second = ($this->project)('Supply of scanners');
    $firstWinner = ($this->bid)($first, 'Printer House', 300000, recommended: true);
    $secondWinner = ($this->bid)($second, 'Scanner Hub', 180000, recommended: true);

    ($this->decide)($firstWinner, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    ($this->decide)($secondWinner, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    app(BidWorkflow::class)->handOffApprovedAward($firstWinner->fresh());

    expect(Award::count())->toBe(2)
        ->and(Award::where('project_id', $first->id)->sole()->bid_id)->toBe($firstWinner->id)
        ->and(Award::where('project_id', $second->id)->sole()->bid_id)->toBe($secondWinner->id);

    testCase()->actingAs($firstWinner->user)->get(route('bidder.awarded-contracts'))->assertOk()
        ->assertSee('Supply of printers')->assertDontSee('Supply of scanners');
});

it('leaves existing award and contract records alone and never replaces an award in force', function () {
    $project = ($this->project)('Supply of medical beds');
    $contracted = ($this->bid)($project, 'Bedmakers Corp.', 900000, recommended: true);
    $contracted->forceFill([
        'award_decision' => Bid::AWARD_DECISION_APPROVED, 'award_decision_at' => now()->subDays(20), 'award_decision_by' => $this->admin->id,
        'notice_of_award_at' => now()->subDays(18), 'contract_signed_at' => now()->subDays(10), 'notice_to_proceed_at' => now()->subDays(8),
        'workflow_step' => Bid::STEP_NOTICE_TO_PROCEED,
    ])->save();
    // Created at the Notice of Award, before the hand-off existed.
    $existing = Award::create([
        'project_id' => $project->id, 'bid_id' => $contracted->id, 'bidder_id' => $contracted->user_id, 'contract_amount' => 900000,
        'contract_date' => now()->subDays(10)->toDateString(), 'notice_of_award_date' => now()->subDays(18)->toDateString(), 'status' => Award::STATUS_VALID,
    ]);
    $before = $existing->fresh()->only(['contract_amount', 'contract_date', 'notice_of_award_date', 'status', 'certificate_status']);

    // A second recommended bid on the same project cannot be approved over the signed contract.
    $rival = ($this->bid)($project, 'Other Beds Inc.', 850000);
    $rival->forceFill(['post_qualification_at' => now()->subDay(), 'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED, 'bac_recommended_at' => now()->subHour(), 'workflow_step' => Bid::STEP_RECOMMENDED])->save();
    expect(app(BidWorkflow::class)->guardError($rival->fresh(), BidWorkflow::APPROVE_AWARD, $this->admin))->not->toBeNull();

    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()->assertSee('Supply of medical beds');
    app(BidWorkflow::class)->handOffApprovedAward($contracted->fresh());

    expect(Award::count())->toBe(1)
        ->and($existing->fresh()->only(array_keys($before)))->toEqual($before)
        ->and($existing->fresh()->award_approved_by)->toBe($this->admin->id);

    // A signed contract is not cancelled through the award cancellation.
    testCase()->actingAs($this->admin)->post(route('admin.awards.cancel', $existing), [
        'cancellation_reason' => 'Testing.', 'cancellation_reference' => 'Memo 1',
    ])->assertSessionHasErrors('cancel');
    expect($existing->fresh()->isCancelled())->toBeFalse();
});

it('cancels an award before contract signing through an audited HoPE action and reopens the other bids', function () {
    $project = ($this->project)('Supply of water pumps');
    $runnerUp = ($this->bid)($project, 'Runner-up Pumps', 410000);
    $winner = ($this->bid)($project, 'Winning Pumps', 400000, recommended: true);
    ($this->decide)($winner, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    $award = Award::sole();
    expect($runnerUp->fresh()->workflow_step)->toBe(Bid::STEP_NOT_AWARDED);

    // Only the HoPE (admin), and only with its reason and authority.
    testCase()->actingAs($this->staff)->post(route('admin.awards.cancel', $award), ['cancellation_reason' => 'x', 'cancellation_reference' => 'y'])->assertForbidden();
    testCase()->actingAs($this->admin)->post(route('admin.awards.cancel', $award), [])->assertSessionHasErrors(['cancellation_reason', 'cancellation_reference']);

    testCase()->actingAs($this->admin)->post(route('admin.awards.cancel', $award), [
        'cancellation_reason' => 'The winning bidder did not post the performance security within the required period.',
        'cancellation_reference' => 'HoPE Memorandum No. 2026-120',
    ])->assertSessionHasNoErrors();

    $award->refresh();
    expect(Award::count())->toBe(1)
        ->and($award->isCancelled())->toBeTrue()
        ->and($award->cancelled_by)->toBe($this->admin->id)
        ->and($award->status)->toBe(Award::STATUS_REVOKED)
        ->and(AuditLog::where('action', 'award_cancelled')->where('auditable_id', $award->id)->exists())->toBeTrue()
        ->and($winner->fresh()->progress()->adminStatus()['key'])->toBe('not_awarded')
        ->and($runnerUp->fresh()->workflow_step)->toBe(Bid::STEP_EVALUATED)
        ->and($runnerUp->fresh()->progress()->isClosed())->toBeFalse()
        ->and(BidTracking::where('bid_id', $winner->id)->where('decision', 'award_cancelled')->exists())->toBeTrue();

    // The winner sees why; the public never saw it; cancelling twice is refused.
    testCase()->actingAs($winner->user)->get(route('bidder.awarded-contracts'))->assertOk()
        ->assertSee('Award cancelled')->assertSee('HoPE Memorandum No. 2026-120');
    testCase()->get(route('public.awards'))->assertDontSee('Supply of water pumps');
    testCase()->actingAs($this->admin)->post(route('admin.awards.cancel', $award), ['cancellation_reason' => 'Again', 'cancellation_reference' => 'Memo'])->assertSessionHasErrors('cancel');

    // The BAC can now recommend and the HoPE approve the runner-up: a new, separate award.
    $runnerUp->fresh()->forceFill(['post_qualification_at' => now(), 'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED, 'bac_recommended_at' => now(), 'workflow_step' => Bid::STEP_RECOMMENDED])->save();
    ($this->decide)($runnerUp->fresh(), BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    expect(Award::count())->toBe(2)
        ->and(Award::active()->sole()->bid_id)->toBe($runnerUp->id);
});

it('downloads generated BAC resolution and post-qualification report for an approved recommendation', function () {
    $project = ($this->project)('Supply of laboratory reagents');
    $bid = ($this->bid)($project, 'Qualified Supplies Inc.', 820000, recommended: true);
    $bid->forceFill([
        'bac_resolution_no' => 'BAC Resolution No. 2026-031',
        'bac_resolution_date' => now()->toDateString(),
        'documents_validated_by' => $this->admin->id,
        'bac_recommended_by' => $this->admin->id,
    ])->save();

    BidTracking::create([
        'bid_id' => $bid->id,
        'bidder_id' => $bid->user_id,
        'project_id' => $project->id,
        'status_title' => 'Post-Qualified',
        'status_description' => 'Passed post-qualification.',
        'status_type' => 'passed',
        'stage' => \App\Support\BidProgress::STAGE_POST_QUALIFICATION,
        'decision' => 'passed',
        'reason' => null,
        'visible_to_bidder' => true,
        'details' => ['findings' => 'Eligibility, experience, financial capacity, and technical requirements were verified.', 'qualification_basis' => 'As stated in the bidding documents.'],
        'created_by' => $this->admin->id,
    ]);

    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.award-recommendation.document', ['bid' => $bid, 'document' => 'resolution']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload($project->reference_no.'-BAC-Resolution.pdf');

    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.award-recommendation.document', ['bid' => $bid, 'document' => 'post-qualification-report']))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload($project->reference_no.'-Post-Qualification-Report.pdf');
});

it('restricts recommendation PDFs to admins and hides them until the bid is post-qualified and recommended', function () {
    $project = ($this->project)('Supply of laboratory reagents');
    $bid = ($this->bid)($project, 'Qualified Supplies Inc.', 820000, recommended: true);
    $bid->forceFill(['bac_resolution_no' => 'BAC Resolution No. 2026-031', 'bac_resolution_date' => now()->toDateString()])->save();

    testCase()->actingAs($this->staff)
        ->get(route('admin.bid.award-recommendation.document', ['bid' => $bid, 'document' => 'resolution']))
        ->assertForbidden();

    $pending = ($this->bid)($project, 'Pending Supplier', 900000);
    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.award-recommendation.document', ['bid' => $pending, 'document' => 'resolution']))
        ->assertNotFound();
});