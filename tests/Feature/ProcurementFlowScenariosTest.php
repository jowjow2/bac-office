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
