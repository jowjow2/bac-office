<?php

use App\Models\Assignment;
use App\Models\Award;
use App\Models\Bid;
use App\Models\BiddingFeePayment;
use App\Models\Project;
use App\Models\User;
use App\Support\BidSubmissionRequirements;
use App\Support\BidWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The procurement as it runs in production: the bids open by themselves at
 * the scheduled time (no manual opening first), every role's pages keep
 * working at each stage, and the alternative paths (failed bidding, award
 * cancelled, small value procurement) reach their end.
 */
beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'scn-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'BAC Secretariat', 'email' => 'scn-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);
    $this->bidderA = User::create(['name' => 'Ana Reyes', 'email' => 'scn-a@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Builders']);
    $this->bidderB = User::create(['name' => 'Ben Cruz', 'email' => 'scn-b@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Occidental Construction']);
    $this->bidderC = User::create(['name' => 'Cora Lim', 'email' => 'scn-c@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Sablayan Supply']);

    $this->pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    $this->local = fn ($date) => $date->format('Y-m-d\TH:i');
    $this->pay = fn (Project $project, User $bidder) => BiddingFeePayment::create([
        'project_id' => $project->id, 'user_id' => $bidder->id, 'amount' => $project->bidding_documents_fee ?? 0,
        'or_number' => 'OR-'.$project->id.'-'.$bidder->id, 'status' => BiddingFeePayment::STATUS_VERIFIED,
        'paid_at' => now()->toDateString(), 'verified_at' => now(), 'recorded_by' => $this->admin->id,
    ]);
    $this->submit = function (Project $project, User $bidder, string $amount) {
        $files = collect(BidSubmissionRequirements::for($project)->requiredKeys())->mapWithKeys(fn ($key) => [$key => ($this->pdf)($key.'.pdf')])->all();

        return testCase()->actingAs($bidder)->post(route('bidder.bids.store', $project), [
            'project_id' => $project->id, 'bid_amount' => $amount, 'documents' => $files,
            'financial_password' => '482913', 'financial_password_confirmation' => '482913',
        ]);
    };
    $this->decide = fn (Bid $bid, string $action, array $extra = []) => testCase()->actingAs($this->admin)
        ->post(route('admin.bid.decision', $bid), array_merge(['action' => $action], $extra));

    // Every role's main pages still load (no server error) at the current stage.
    $this->pagesLoad = function (string $stage) {
        foreach ([
            [$this->admin, ['admin.dashboard', 'admin.projects', 'admin.bids', 'admin.awards.index', 'admin.reports', 'admin.payments', 'admin.audit-logs']],
            [$this->staff, ['staff.dashboard']],
            [$this->bidderA, ['bidder.dashboard', 'bidder.available-projects', 'bidder.my-bids', 'bidder.awarded-contracts']],
        ] as [$user, $routes]) {
            foreach ($routes as $name) {
                $status = testCase()->actingAs($user)->get(route($name))->getStatusCode();
                expect($status)->toBeLessThan(500, "{$name} failed ({$status}) at stage: {$stage}");
            }
        }
        $track = testCase()->actingAs($this->bidderA)->getJson(route('bidder.bidding-track.data'));
        expect($track->getStatusCode())->toBe(200, "bidding track failed at stage: {$stage}");
    };

    $this->competitive = function (array $overrides = []) {
        testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), array_merge([
            'title' => 'Concreting of Brgy. Bubog Farm-to-Market Road',
            'description' => 'Concreting of 2.1 km farm-to-market road.',
            'category' => 'infrastructure', 'location' => 'Brgy. Bubog, San Jose', 'procurement_mode' => 'public_bidding',
            'source_of_fund' => '20% Development Fund', 'contract_duration' => '120 calendar days', 'budget' => 900000,
            'status' => 'open', 'end_user_unit' => 'Municipal Engineering Office', 'date_posted' => now()->toDateString(),
            'bid_submission_deadline' => ($this->local)(workdayAt(10, 9, 0)),
            'bid_opening_date' => ($this->local)(workdayAt(10, 9, 30)),
            'submission_mode' => 'electronic', 'electronic_submission_authority' => 'BAC Resolution No. 2026-014',
            'award_criterion' => 'lowest_calculated_bid', 'bid_opening_venue' => 'BAC Conference Room, Municipal Hall',
            'document_type' => ['invitation_to_bid', 'bidding_documents'],
            'project_documents' => [($this->pdf)('ITB.pdf'), ($this->pdf)('PBD.pdf')],
            'confirm_correct' => 'on',
        ], $overrides))->assertSessionHasNoErrors();
        $project = Project::latest('id')->firstOrFail();
        Assignment::create(['staff_id' => $this->staff->id, 'project_id' => $project->id, 'role_in_project' => 'BAC Secretariat']);
        testCase()->actingAs($this->admin)->post(route('admin.project.bid-opening-rules', $project), [
            'award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'PBD, Section III',
        ])->assertSessionHasNoErrors();

        return $project->fresh(['schedule']);
    };
});

it('runs a competitive bidding from posting to completion with the scheduled opening', function () {
    $project = ($this->competitive)();
    ($this->pagesLoad)('published');

    foreach ([[$this->bidderA, '850,000.00'], [$this->bidderB, '870,000.00']] as [$bidder, $amount]) {
        ($this->pay)($project, $bidder);
        ($this->submit)($project, $bidder, $amount)->assertSessionHasNoErrors();
    }
    $bidA = Bid::where('user_id', $this->bidderA->id)->firstOrFail();
    $bidB = Bid::where('user_id', $this->bidderB->id)->firstOrFail();
    ($this->pagesLoad)('bids submitted');

    // Past the deadline, before the opening: closed for submission, still sealed.
    $this->travelTo($project->schedule->bid_opening_date->copy()->subMinutes(10));
    ($this->pay)($project, $this->bidderC);
    ($this->submit)($project, $this->bidderC, '800,000.00')->assertSessionHasErrors();
    expect($bidA->fresh()->isSealed())->toBeTrue();
    ($this->pagesLoad)('awaiting opening');

    // At the opening time, the first request of anyone opens the bids.
    $this->travelTo($project->schedule->bid_opening_date->copy()->addMinute());
    testCase()->actingAs($this->bidderB)->get(route('bidder.dashboard'))->assertOk();
    $project->refresh();
    expect($project->bidsAreOpened())->toBeTrue()->and($project->status)->toBe('closed');
    ($this->pagesLoad)('opened automatically');

    // Technical review, financial opening, evaluation.
    $keys = BidSubmissionRequirements::for($project)->requiredKeys();
    foreach ([$bidA, $bidB] as $bid) {
        ($this->decide)($bid, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => $keys])->assertSessionHasNoErrors();
        testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $bid), ['opening_password' => '482913'])->assertSessionHasNoErrors();
        ($this->decide)($bid, BidWorkflow::START_EVALUATION)->assertSessionHasNoErrors();
        ($this->decide)($bid, BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the project criteria.'])->assertSessionHasNoErrors();
    }
    ($this->pagesLoad)('evaluated');

    // Post-qualification of the lowest, recommendation, HoPE approval.
    ($this->decide)($bidA, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Documents and qualifications verified.'])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::RECOMMEND, ['bac_resolution_no' => 'BAC Resolution No. 2026-031', 'bac_resolution_date' => now()->toDateString()])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    ($this->pagesLoad)('award approved');

    testCase()->actingAs($this->admin)->post(route('admin.awards.declare', $project), ['bid_id' => $bidA->id, 'certificate_file' => ($this->pdf)('NOA.pdf')])->assertSessionHasNoErrors();
    expect($project->fresh()->status)->toBe('awarded');
    ($this->pagesLoad)('notice of award');

    ($this->decide)($bidA, BidWorkflow::CONTRACT_SIGNED, ['performance_security_at' => now()->toDateString(), 'contract_date' => now()->toDateString()])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::NOTICE_TO_PROCEED)->assertSessionHasNoErrors();
    ($this->pagesLoad)('notice to proceed');

    // Inspection and acceptance complete the contract.
    $this->travel(30)->days();
    $local = fn ($date) => $date->copy()->timezone(config('bac-office.display_timezone'))->format('Y-m-d\TH:i');
    testCase()->actingAs($this->staff)->post(route('staff.procurement.inspection', $project), ['occurred_at' => $local(now()->subDays(2)), 'reference_no' => 'IR-001'])->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.procurement.acceptance', $project), ['occurred_at' => $local(now()->subDay()), 'reference_no' => 'IAR-001'])->assertSessionHasNoErrors();
    expect($project->fresh()->isCompleted())->toBeTrue();
    ($this->pagesLoad)('completed');

    $stages = collect(testCase()->actingAs($this->admin)->get(route('admin.reports'))->viewData('pipeline')['stages'])->keyBy('key');
    expect($stages['completed']['reached'])->toBe(1);
});

it('awards the next bidder after the first award is cancelled', function () {
    $project = ($this->competitive)();
    foreach ([[$this->bidderA, '850,000.00'], [$this->bidderB, '870,000.00']] as [$bidder, $amount]) {
        ($this->pay)($project, $bidder);
        ($this->submit)($project, $bidder, $amount)->assertSessionHasNoErrors();
    }
    $bidA = Bid::where('user_id', $this->bidderA->id)->firstOrFail();
    $bidB = Bid::where('user_id', $this->bidderB->id)->firstOrFail();
    $this->travelTo($project->schedule->bid_opening_date->copy()->addMinute());
    testCase()->get('/')->assertOk();

    $keys = BidSubmissionRequirements::for($project)->requiredKeys();
    foreach ([$bidA, $bidB] as $bid) {
        ($this->decide)($bid, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => $keys])->assertSessionHasNoErrors();
        testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $bid), ['opening_password' => '482913'])->assertSessionHasNoErrors();
        ($this->decide)($bid, BidWorkflow::START_EVALUATION)->assertSessionHasNoErrors();
        ($this->decide)($bid, BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the project criteria.'])->assertSessionHasNoErrors();
    }
    ($this->decide)($bidA, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Documents and qualifications verified.'])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::RECOMMEND, ['bac_resolution_no' => 'BAC Res. 2026-031', 'bac_resolution_date' => now()->toDateString()])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.awards.declare', $project), ['bid_id' => $bidA->id, 'certificate_file' => ($this->pdf)('NOA.pdf')])->assertSessionHasNoErrors();

    // The winner refuses to sign: the HoPE cancels the award before the contract.
    $award = Award::where('bid_id', $bidA->id)->firstOrFail();
    testCase()->actingAs($this->admin)->post(route('admin.awards.cancel', $award), [
        'cancellation_reason' => 'Winning bidder refused to sign the contract.', 'cancellation_reference' => 'HoPE Memo 2026-12',
    ])->assertSessionHasNoErrors();
    ($this->pagesLoad)('award cancelled');

    // The second-ranked bidder goes through post-qualification and is awarded.
    ($this->decide)($bidB->fresh(), BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    ($this->decide)($bidB->fresh(), BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Documents and qualifications verified.'])->assertSessionHasNoErrors();
    ($this->decide)($bidB->fresh(), BidWorkflow::RECOMMEND, ['bac_resolution_no' => 'BAC Res. 2026-040', 'bac_resolution_date' => now()->toDateString()])->assertSessionHasNoErrors();
    ($this->decide)($bidB->fresh(), BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.awards.declare', $project), ['bid_id' => $bidB->id, 'certificate_file' => ($this->pdf)('NOA-2.pdf')])->assertSessionHasNoErrors();
    expect($project->fresh()->status)->toBe('awarded')
        ->and(Award::where('bid_id', $bidB->id)->exists())->toBeTrue();
    ($this->pagesLoad)('second award');
});

it('declares a failed bidding when no bid is received and links the new round', function () {
    $project = ($this->competitive)();
    $this->travelTo($project->schedule->bid_opening_date->copy()->addMinute());
    testCase()->get('/')->assertOk();

    testCase()->actingAs($this->admin)->post(route('admin.project.failed-bidding', $project), ['failed_bidding_reason' => 'No bids were received.'])->assertSessionHasNoErrors();
    expect($project->fresh()->isFailedBidding())->toBeTrue();
    ($this->pagesLoad)('failed bidding');
});

it('runs a small value procurement from quotations to award, and can fail it', function () {
    $svp = function (string $title) {
        testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), [
            'title' => $title, 'description' => 'Supply of office equipment.', 'category' => 'goods', 'location' => 'Municipal Hall',
            'procurement_mode' => 'small_value_procurement', 'legal_basis' => 'ra_12009', 'source_of_fund' => 'General Fund',
            'contract_duration' => '30 calendar days', 'budget' => 350000, 'status' => 'open', 'end_user_unit' => 'MPDO',
            'date_posted' => now()->toDateString(), 'bid_submission_deadline' => ($this->local)(workdayAt(5, 10, 0)),
            'submission_mode' => 'electronic', 'electronic_submission_authority' => 'BAC Resolution No. 2026-014',
            'document_type' => ['invitation_to_bid'], 'project_documents' => [($this->pdf)('RFQ.pdf')], 'confirm_correct' => 'on',
        ])->assertSessionHasNoErrors();

        return Project::latest('id')->firstOrFail()->fresh(['schedule']);
    };

    $project = $svp('Supply of laptops');
    foreach ([[$this->bidderA, '330,000.00'], [$this->bidderB, '340,000.00']] as [$bidder, $amount]) {
        ($this->pay)($project, $bidder);
        ($this->submit)($project, $bidder, $amount)->assertSessionHasNoErrors();
    }
    $bidA = Bid::where('project_id', $project->id)->where('user_id', $this->bidderA->id)->firstOrFail();

    $this->travelTo($project->bidSubmissionDeadline()->copy()->addHour());
    ($this->pagesLoad)('SVP after deadline');
    $keys = BidSubmissionRequirements::for($project)->requiredKeys();
    ($this->decide)($bidA, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => $keys])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::START_EVALUATION)->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Lowest calculated and responsive quotation.'])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Documents verified.'])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::RECOMMEND, ['bac_resolution_no' => 'BAC Res. 2026-050', 'bac_resolution_date' => now()->toDateString()])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.awards.declare', $project), ['bid_id' => $bidA->id, 'certificate_file' => ($this->pdf)('NOA.pdf')])->assertSessionHasNoErrors();
    expect($project->fresh()->status)->toBe('awarded');
    ($this->pagesLoad)('SVP awarded');

    // A second SVP with no quotation must be declarable as failed.
    $this->travelBack();
    $empty = $svp('Supply of printers');
    $this->travelTo($empty->bidSubmissionDeadline()->copy()->addHour());
    testCase()->actingAs($this->admin)->post(route('admin.project.failed-bidding', $empty), ['failed_bidding_reason' => 'No quotation was received.'])->assertSessionHasNoErrors();
    expect($empty->fresh()->isFailedBidding())->toBeTrue();
});

/** Opening and evaluation of the bids received, lowest first; returns them refreshed. */
function scenarioOpenAndEvaluate(object $test, Project $project): array
{
    $bids = Bid::where('project_id', $project->id)->orderBy('bid_amount')->get();
    $test->travelTo($project->schedule->bid_opening_date->copy()->addMinute());
    testCase()->get('/')->assertOk();
    $keys = BidSubmissionRequirements::for($project)->requiredKeys();
    foreach ($bids as $bid) {
        ($test->decide)($bid, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => $keys])->assertSessionHasNoErrors();
        if ($bid->submission_channel === Bid::CHANNEL_MANUAL) {
            // A sealed paper bid has no PIN; the modal offers to record the envelope opened.
            testCase()->actingAs($test->admin)->withHeader('X-Requested-With', 'XMLHttpRequest')->get(route('admin.bid.view', $bid))
                ->assertSee('Record financial envelope opened');
            testCase()->actingAs($test->admin)->post(route('admin.bid.open-financial', $bid))->assertSessionHasErrors('bid_amount');
            $read = number_format(800000 + $bid->id * 10000, 2);
            testCase()->actingAs($test->admin)->post(route('admin.bid.open-financial', $bid), ['bid_amount' => $read])->assertSessionHasNoErrors();
            expect($bid->fresh()->bid_amount)->toBe(str_replace(',', '', $read));
            expect($bid->fresh()->financial_opening_method)->toBe('sealed_envelope');
        } else {
            testCase()->actingAs($test->admin)->post(route('admin.bid.open-financial', $bid), ['opening_password' => '482913'])->assertSessionHasNoErrors();
        }
        ($test->decide)($bid, BidWorkflow::START_EVALUATION)->assertSessionHasNoErrors();
        ($test->decide)($bid, BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the project criteria.'])->assertSessionHasNoErrors();
    }

    return $bids->map->fresh()->all();
}

/** Two online bids (A lower than B), opened and evaluated responsive. */
function scenarioEvaluatedBids(object $test, Project $project): array
{
    foreach ([[$test->bidderA, '850,000.00'], [$test->bidderB, '870,000.00']] as [$bidder, $amount]) {
        ($test->pay)($project, $bidder);
        ($test->submit)($project, $bidder, $amount)->assertSessionHasNoErrors();
    }

    return scenarioOpenAndEvaluate($test, $project);
}

it('awards the next bidder after the HoPE disapproves the recommendation', function () {
    $project = ($this->competitive)();
    [$bidA, $bidB] = scenarioEvaluatedBids($this, $project);

    ($this->decide)($bidA, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Documents verified.'])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::RECOMMEND, ['bac_resolution_no' => 'BAC Res. 2026-031', 'bac_resolution_date' => now()->toDateString()])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::DISAPPROVE_AWARD, ['reason' => 'Unresolved slippage on another LGU contract.'])->assertSessionHasNoErrors();
    ($this->pagesLoad)('award disapproved');

    ($this->decide)($bidB->fresh(), BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    ($this->decide)($bidB->fresh(), BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Documents verified.'])->assertSessionHasNoErrors();
    ($this->decide)($bidB->fresh(), BidWorkflow::RECOMMEND, ['bac_resolution_no' => 'BAC Res. 2026-041', 'bac_resolution_date' => now()->toDateString()])->assertSessionHasNoErrors();
    ($this->decide)($bidB->fresh(), BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.awards.declare', $project), ['bid_id' => $bidB->id, 'certificate_file' => ($this->pdf)('NOA.pdf')])->assertSessionHasNoErrors();
    expect($project->fresh()->status)->toBe('awarded');
});

it('moves to the next bidder after post-disqualification, and fails the bidding when all fail', function () {
    $project = ($this->competitive)();
    [$bidA, $bidB] = scenarioEvaluatedBids($this, $project);

    // The second bidder waits for the first.
    ($this->decide)($bidB, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasErrors('milestone');
    ($this->decide)($bidA, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::FAIL_POST_QUALIFICATION, ['reason' => 'Net financial contracting capacity below the ABC.', 'post_qualification_findings' => 'NFCC computed below the ABC.'])->assertSessionHasNoErrors();
    ($this->decide)($bidB->fresh(), BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    ($this->decide)($bidB->fresh(), BidWorkflow::FAIL_POST_QUALIFICATION, ['reason' => 'Expired business permit.', 'post_qualification_findings' => 'Business permit expired.'])->assertSessionHasNoErrors();
    ($this->pagesLoad)('all post-disqualified');

    // Nobody left: failure of bidding, then the new round is linked to it.
    testCase()->actingAs($this->admin)->post(route('admin.project.failed-bidding', $project), ['failed_bidding_reason' => 'All bidders were post-disqualified.'])->assertSessionHasNoErrors();
    $rebid = ($this->competitive)(['title' => 'Concreting of Brgy. Bubog FMR (rebid)']);
    testCase()->actingAs($this->admin)->post(route('admin.project.failed-bidding', $project), ['failed_bidding_reason' => 'All bidders were post-disqualified.', 'rebid_project_id' => $rebid->id])->assertSessionHasNoErrors();
    expect($project->fresh()->rebid_project_id)->toBe($rebid->id);
    ($this->pagesLoad)('rebid linked');
});

it('never opens an online financial bid without the bidder PIN', function () {
    $project = ($this->competitive)();
    ($this->pay)($project, $this->bidderA);
    ($this->submit)($project, $this->bidderA, '850,000.00')->assertSessionHasNoErrors();
    $bid = Bid::where('project_id', $project->id)->firstOrFail();
    $this->travelTo($project->schedule->bid_opening_date->copy()->addMinute());
    ($this->decide)($bid, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => BidSubmissionRequirements::for($project)->requiredKeys()])->assertSessionHasNoErrors();

    // The sealed-envelope path is only for paper bids.
    testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $bid))->assertSessionHasErrors('opening_password');
    testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $bid), ['opening_password' => '000000'])->assertSessionHasErrors('opening');
    expect($bid->fresh()->isFinancialSealed())->toBeTrue();
});

it('lets a bidder modify an online bid before the deadline, never after', function () {
    $project = ($this->competitive)();
    ($this->pay)($project, $this->bidderA);
    ($this->submit)($project, $this->bidderA, '850,000.00')->assertSessionHasNoErrors();
    ($this->submit)($project, $this->bidderA, '845,000.00')->assertSessionHasNoErrors();
    expect(Bid::where('project_id', $project->id)->count())->toBe(1)
        ->and((float) Bid::where('project_id', $project->id)->first()->bid_amount)->toBe(845000.0);

    $this->travelTo($project->bidSubmissionDeadline()->copy()->addMinute());
    ($this->submit)($project, $this->bidderA, '800,000.00')->assertSessionHasErrors();
    expect((float) Bid::where('project_id', $project->id)->first()->bid_amount)->toBe(845000.0);
});

it('receives sealed paper bids on a manual project and carries them to award', function () {
    $project = ($this->competitive)(['submission_mode' => 'manual', 'electronic_submission_authority' => null, 'submission_venue' => 'BAC Secretariat, Municipal Hall']);
    expect($project->submission_mode)->toBe(Project::SUBMISSION_MANUAL);
    $received = now()->timezone(config('bac-office.display_timezone'))->format('Y-m-d\TH:i');
    foreach ([[$this->bidderA, '850,000.00', 'LOG-001'], [$this->bidderB, '870,000.00', 'LOG-002']] as [$bidder, $amount, $log]) {
        ($this->pay)($project, $bidder);
        ($this->submit)($project, $bidder, $amount)->assertSessionHasNoErrors();
        $bid = Bid::where('project_id', $project->id)->where('user_id', $bidder->id)->firstOrFail();
        expect($bid->isDraft())->toBeTrue();
        ($this->decide)($bid, BidWorkflow::RECORD_MANUAL_RECEIPT, ['receipt_no' => $log, 'received_at' => $received])->assertSessionHasNoErrors();
    }
    ($this->pagesLoad)('sealed bids received');

    [$bidA] = scenarioOpenAndEvaluate($this, $project);
    ($this->decide)($bidA, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Documents verified.'])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::RECOMMEND, ['bac_resolution_no' => 'BAC Res. 2026-060', 'bac_resolution_date' => now()->toDateString()])->assertSessionHasNoErrors();
    ($this->decide)($bidA, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.awards.declare', $project), ['bid_id' => $bidA->id, 'certificate_file' => ($this->pdf)('NOA.pdf')])->assertSessionHasNoErrors();
    expect($project->fresh()->status)->toBe('awarded');
});

it('records a sealed bid handed in by a walk-in bidder who saved nothing online', function () {
    $project = ($this->competitive)(['submission_mode' => 'manual', 'electronic_submission_authority' => null, 'submission_venue' => 'BAC Secretariat, Municipal Hall']);
    $this->bidderC->bidderProfile()->create(['company_name' => 'Sablayan Supply', 'contact_person' => 'Cora', 'contact_number' => '09171234567', 'business_address' => 'Sablayan', 'approval_status' => 'approved']);
    $received = now()->timezone(config('bac-office.display_timezone'))->format('Y-m-d\TH:i');
    $record = fn (array $data) => testCase()->actingAs($this->admin)->from(route('admin.projects'))
        ->post(route('admin.project.sealed-bids.store', $project), $data + ['bidder_id' => $this->bidderC->id, 'receipt_no' => 'LOG-WALK-1', 'received_at' => $received]);

    // The bidding documents fee comes first.
    $record([])->assertSessionHas('error', fn ($message) => str_contains($message, 'fee'));
    expect(Bid::where('project_id', $project->id)->count())->toBe(0);
    ($this->pay)($project, $this->bidderC);

    // The view offers the bidder, and the receipt is recorded as an official bid.
    testCase()->actingAs($this->admin)->get(route('admin.project.view', $project))->assertOk()
        ->assertSee('Sealed Bids Received')->assertSee('Sablayan Supply');
    $record([])->assertRedirect(route('admin.projects'))->assertSessionHas('success', fn ($message) => str_contains($message, 'LOG-WALK-1'));
    $bid = Bid::where('project_id', $project->id)->where('user_id', $this->bidderC->id)->sole();
    expect($bid->isDraft())->toBeFalse()->and($bid->receipt_no)->toBe('LOG-WALK-1')->and($bid->isSealed())->toBeTrue();

    // Once only, and never after the deadline.
    $record(['receipt_no' => 'LOG-WALK-2'])->assertSessionHas('error', fn ($message) => str_contains($message, 'already has an official bid'));
    $this->bidderA->bidderProfile()->create(['company_name' => 'Mindoro Builders', 'contact_person' => 'Ana', 'contact_number' => '09171234567', 'business_address' => 'San Jose', 'approval_status' => 'approved']);
    ($this->pay)($project, $this->bidderA);
    $late = $project->bidSubmissionDeadline()->copy()->addMinutes(5);
    $this->travelTo($late->copy()->addMinute());
    $record(['bidder_id' => $this->bidderA->id, 'receipt_no' => 'LOG-LATE', 'received_at' => $late->timezone(config('bac-office.display_timezone'))->format('Y-m-d\TH:i')])
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'after the submission deadline'));

    // At the opening, the price read from the envelope becomes the bid amount.
    [$opened] = scenarioOpenAndEvaluate($this, $project);
    expect((float) $opened->bid_amount)->toBeGreaterThan(0)->and($opened->financial_opening_method)->toBe('sealed_envelope');
    ($this->pagesLoad)('walk-in sealed bid evaluated');
});