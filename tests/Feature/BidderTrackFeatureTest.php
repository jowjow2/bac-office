<?php

use App\Models\Assignment;
use App\Models\Award;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\BidTracking;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidHistory;
use App\Support\BidProgress;
use App\Support\BidWorkflow;
use App\Support\BidSubmissionRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();

    $this->admin = User::create([
        'name' => 'BAC Admin',
        'email' => 'track-admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $this->project = Project::create([
        'title' => 'San Jose Farm-to-Market Road',
        'description' => 'Concreting of barangay road.',
        'budget' => 1000000,
        'deadline' => now()->subDay(),
        'status' => 'open',
        'award_criterion' => 'lowest_calculated_bid',
        'opening_documents_reference' => 'Signed bidding documents',
    ]);

    // The project's own bidding requirements drive the preliminary checklist.
    ProjectRequirement::create([
        'project_id' => $this->project->id,
        'required_documents' => ['Technical Proposal', 'Financial Proposal'],
    ]);

    $this->makeBidder = function (string $key): User {
        return User::create([
            'name' => "Bidder {$key}",
            'email' => "bidder-{$key}@example.com",
            'password' => Hash::make('password'),
            'role' => 'bidder',
            'status' => 'active',
            'company' => "Builder {$key}",
        ]);
    };

    $this->makeBid = function (User $bidder, array $attributes = []): Bid {
        return Bid::create(array_merge([
            'project_id' => $this->project->id,
            'user_id' => $bidder->id,
            'bid_amount' => 950000,
            'proposal_file' => 'proposals/'.$bidder->id.'.pdf',
            'status' => 'pending',
            'workflow_step' => Bid::STEP_SUBMITTED,
            'financial_opening_password_hash' => Hash::make('Opening-password-2026'),
        ], $attributes));
    };

    $this->openBids = fn () => testCase()->actingAs($this->admin)
        ->post(route('admin.project.open-bids', $this->project))
        ->assertSessionHasNoErrors();

    // Record a decision the way the Review Bid modal does.
    $this->decide = fn (Bid $bid, string $action, array $extra = []) => testCase()
        ->actingAs($this->admin)
        ->post(route('admin.bid.decision', $bid), array_merge(['action' => $action], $extra));

    $this->passPrelim = function (Bid $bid) {
        ($this->decide)($bid, BidWorkflow::PASS_PRELIMINARY, [
            'verified_requirements' => ['technical_proposal', 'financial_proposal'],
        ])->assertSessionHasNoErrors();
        testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $bid), [
            'opening_password' => 'Opening-password-2026',
        ])->assertSessionHasNoErrors();
    };

    $this->advanceToRecommended = function (Bid $bid) {
        ($this->passPrelim)($bid);
        ($this->decide)($bid, BidWorkflow::START_EVALUATION)->assertSessionHasNoErrors();
    ($this->decide)($bid, BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the configured project criteria.'])->assertSessionHasNoErrors();
        ($this->decide)($bid, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
        ($this->decide)($bid, BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Post-qualification documents and qualifications verified.'])->assertSessionHasNoErrors();
        ($this->decide)($bid, BidWorkflow::RECOMMEND, [
            'bac_resolution_no' => 'BAC Resolution No. 2026-031',
            'bac_resolution_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
    };

    $this->trackFor = fn (User $bidder) => testCase()->actingAs($bidder)
        ->getJson(route('bidder.bidding-track.data'))
        ->assertOk()
        ->json('bids.0');

    // What the admin table shows for this bid (same source as filter and export).
    $this->adminStage = fn (Bid $bid) => Bid::find($bid->id)->progress()->adminStatus()['label'];

    $this->stateOf = fn (array $track, string $stage) => collect($track['stages'])->firstWhere('key', $stage)['state'] ?? null;
});

it('keeps bids sealed until the authorized bid opening', function () {
    $bidder = ($this->makeBidder)('sealed');
    $bid = ($this->makeBid)($bidder);

    testCase()->actingAs($this->admin)->get(route('admin.bids'))
        ->assertOk()
        ->assertSee('Submitted')
        ->assertSee('Sealed')
        ->assertDontSee('950,000.00');
    testCase()->actingAs($this->admin)->get(route('admin.bid.document.pdf', ['bid' => $bid, 'document' => 'proposal']))->assertForbidden();

    // Nothing can be examined before opening.
    ($this->decide)($bid, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => ['technical_proposal', 'financial_proposal']])
        ->assertSessionHasErrors('milestone');
    expect(($this->trackFor)($bidder)['current']['label'])->toBe('Submitted - Awaiting Bid Opening');

    // Opening is refused before the scheduled opening time.
    ProjectSchedule::create(['project_id' => $this->project->id, 'bid_opening_date' => now()->addHours(3)]);
    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $this->project))->assertSessionHasErrors('bids_opened_at');

    $this->travel(4)->hours();
    ($this->openBids)();

    expect(($this->adminStage)($bid))->toBe('Preliminary Examination');
    // Two-envelope rule: the price opens only after preliminary examination is passed.
    testCase()->actingAs($this->admin)->get(route('admin.bids'))->assertDontSee('950,000.00');

    $track = ($this->trackFor)($bidder);
    expect($track['current']['label'])->toBe('Under Preliminary Examination')
        ->and(collect($track['history'])->pluck('title'))->toContain('Technical Components Opened');

    ($this->passPrelim)($bid);
    testCase()->actingAs($this->admin)->get(route('admin.bids'))->assertSee('950,000.00');
});

it('refuses to open bids before the submission deadline', function () {
    $this->project->update(['deadline' => now()->addDay(), 'status' => 'open']);

    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $this->project))
        ->assertSessionHasErrors('bids_opened_at');

    expect($this->project->fresh()->bids_opened_at)->toBeNull();
});

it('requires every project requirement to be verified before passing preliminary examination', function () {
    $bidder = ($this->makeBidder)('verify');
    $bid = ($this->makeBid)($bidder);
    $bid->update(['submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subDays(2)]);
    $technical = BidSubmissionRequirements::for($this->project)->technical()->where('required', true);
    foreach ($technical as $item) {
        BidDocument::create([
            'bid_id' => $bid->id, 'requirement_key' => $item['key'], 'component' => BidDocument::COMPONENT_TECHNICAL,
            'label' => $item['label'], 'file_path' => 'bids/'.$item['key'].'.pdf', 'original_name' => $item['key'].'.pdf', 'size' => 10,
        ]);
    }
    $requiredKeys = $technical->pluck('key')->all();
    ($this->openBids)();

    // An uploaded file alone is not a pass.
    ($this->decide)($bid, BidWorkflow::PASS_PRELIMINARY)->assertSessionHasErrors('verified_requirements');
    ($this->decide)($bid, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => ['technical_proposal']])
        ->assertSessionHasErrors('verified_requirements');

    // A missing required document blocks a pass even if ticked.
    $missing = ($this->makeBid)(($this->makeBidder)('missing'), ['proposal_file' => null, 'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subDays(2)]);
    ($this->decide)($missing, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => $requiredKeys])
        ->assertSessionHasErrors('verified_requirements');

    ($this->decide)($bid, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => $requiredKeys])->assertSessionHasNoErrors();

    expect(($this->adminStage)($bid))->toBe('Bid Evaluation');
    $event = BidTracking::where('bid_id', $bid->id)->where('decision', 'passed')->first();
    expect($event->stage)->toBe(BidProgress::STAGE_PRELIMINARY)
        ->and($event->created_by)->toBe($this->admin->id)
        ->and(collect($event->details['requirements'])->pluck('result')->unique()->all())->toBe(['passed']);

    // Bidder: Under Evaluation, never "Approved"; history without the reviewer's identity.
    $response = testCase()->actingAs($bidder)->getJson(route('bidder.bidding-track.data'))->assertOk();
    $track = $response->json('bids.0');
    expect($track['current']['label'])->toBe('Under Evaluation')
        ->and(collect($track['history'])->pluck('title'))->toContain('Passed Preliminary Examination')
        ->and(collect($track['history'])->pluck('actor')->filter()->all())->toBe([])
        ->and($response->getContent())->not->toContain('BAC Admin')
        ->and(collect($track['history'])->pluck('details')->filter()->all())->toBe([]);
});

it('records a failed preliminary examination with a required, bidder-visible reason', function () {
    $bidder = ($this->makeBidder)('failed-prelim');
    $bid = ($this->makeBid)($bidder, ['notes' => 'INTERNAL: committee deliberation notes']);
    ($this->openBids)();

    ($this->decide)($bid, BidWorkflow::FAIL_PRELIMINARY)->assertSessionHasErrors('reason');
    ($this->decide)($bid, BidWorkflow::FAIL_PRELIMINARY, [
        'reason' => 'Financial proposal is unsigned.',
        'failed_requirements' => ['financial_proposal'],
    ])->assertSessionHasNoErrors();

    expect(($this->adminStage)($bid))->toBe('Disqualified');

    $response = testCase()->actingAs($bidder)->getJson(route('bidder.bidding-track.data'));
    $track = $response->json('bids.0');
    expect($track['outcome']['title'])->toBe('Disqualified at Bid Opening / Preliminary Examination')
        ->and($track['outcome']['message'])->toBe('Financial proposal is unsigned.')
        ->and(($this->stateOf)($track, BidProgress::STAGE_PRELIMINARY))->toBe('failed')
        ->and(collect($track['history'])->firstWhere('title', 'Failed Preliminary Examination')['reason'])->toBe('Financial proposal is unsigned.')
        ->and($response->getContent())->not->toContain('INTERNAL');

    // The admin modal shows stage, decision, reason and the responsible user.
    testCase()->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Failed Preliminary Examination')
        ->assertSee('Financial proposal is unsigned.')
        ->assertSee('BAC Admin (Admin)')
        ->assertSee('No further decision can be recorded');
});

it('shows a post-disqualified bidder on both the admin table and the bidder track', function () {
    $bidder = ($this->makeBidder)('post-dq');
    $bid = ($this->makeBid)($bidder);
    ($this->openBids)();
    ($this->passPrelim)($bid);
    ($this->decide)($bid, BidWorkflow::START_EVALUATION)->assertSessionHasNoErrors();
    ($this->decide)($bid, BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the configured project criteria.'])->assertSessionHasNoErrors();
    ($this->decide)($bid, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();

    expect(($this->adminStage)($bid))->toBe('Post-Qualification')
        ->and(($this->trackFor)($bidder)['current']['label'])->toBe('Under Post-Qualification');

    ($this->decide)($bid, BidWorkflow::FAIL_POST_QUALIFICATION, ['reason' => 'Net financial contracting capacity is insufficient.', 'post_qualification_findings' => 'Net financial contracting capacity is insufficient.'])
        ->assertSessionHasNoErrors();

    expect(($this->adminStage)($bid))->toBe('Disqualified');
    $track = ($this->trackFor)($bidder);
    expect($track['outcome']['title'])->toBe('Disqualified at Post-Qualification')
        ->and($track['outcome']['message'])->toBe('Net financial contracting capacity is insufficient.')
        ->and(($this->stateOf)($track, BidProgress::STAGE_RECOMMENDATION))->toBeNull();

    ($this->decide)($bid, BidWorkflow::RECOMMEND)->assertSessionHasErrors('milestone');
});

it('walks the winning bidder through each authorized action and closes the others as not awarded', function () {
    $winner = ($this->makeBidder)('winner');
    $loser = ($this->makeBidder)('loser');
    $winningBid = ($this->makeBid)($winner);
    $losingBid = ($this->makeBid)($loser, ['bid_amount' => 980000]);
    ($this->openBids)();

    ($this->passPrelim)($losingBid);
    ($this->decide)($losingBid, BidWorkflow::START_EVALUATION)->assertSessionHasNoErrors();
    ($this->decide)($losingBid, BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the configured project criteria.'])->assertSessionHasNoErrors();

    // Stages cannot be skipped.
    ($this->decide)($winningBid, BidWorkflow::NOTICE_OF_AWARD)->assertSessionHasErrors('milestone');

    ($this->advanceToRecommended)($winningBid);
    expect(($this->adminStage)($winningBid))->toBe('BAC Recommendation')
        ->and(($this->trackFor)($winner)['current']['label'])->toBe('Recommended for Award');

    // HoPE decisions are not available to BAC staff.
    $staff = User::create(['name' => 'BAC Staff', 'email' => 'bac-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);
    expect(app(BidWorkflow::class)->availableActions($winningBid->fresh(), $staff))->not->toHaveKey(BidWorkflow::APPROVE_AWARD);
    expect(fn () => app(BidWorkflow::class)->apply($winningBid->fresh(), BidWorkflow::APPROVE_AWARD, $staff))
        ->toThrow(ValidationException::class);

    ($this->decide)($winningBid, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();

    expect(($this->adminStage)($winningBid))->toBe('Award Approval')
        ->and(($this->trackFor)($winner)['outcome'])->toBeNull() // not "Awarded" before the Notice of Award
        ->and(($this->adminStage)($losingBid))->toBe('Not Awarded');

    $loserTrack = ($this->trackFor)($loser);
    expect($loserTrack['outcome']['key'])->toBe('not_awarded')
        ->and($loserTrack['outcome']['message'])->toContain('was not disqualified')
        ->and(collect($loserTrack['history'])->pluck('title'))->toContain('Not Awarded');

    // The Notice of Award needs the signed NOA document; it creates the award record.
    Storage::fake('local');
    ($this->decide)($winningBid, BidWorkflow::NOTICE_OF_AWARD)->assertSessionHasErrors('notice_file');
    ($this->decide)($winningBid, BidWorkflow::NOTICE_OF_AWARD, [
        'notice_file' => UploadedFile::fake()->createWithContent('noa.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"),
    ])->assertSessionHasNoErrors();
    expect(($this->adminStage)($winningBid))->toBe('Notice of Award Issued')
        ->and(($this->trackFor)($winner)['outcome']['key'])->toBe('awarded')
        ->and($this->project->fresh()->status)->toBe('awarded');

    $award = Award::where('bid_id', $winningBid->id)->firstOrFail();
    expect($award->notice_of_award_date->isToday())->toBeTrue()
        ->and($award->contract_date)->toBeNull()
        ->and((float) $award->contract_amount)->toBe((float) $winningBid->bid_amount);

    ($this->decide)($winningBid, BidWorkflow::CONTRACT_SIGNED, ['contract_date' => now()->toDateString()])->assertSessionHasErrors('performance_security_at');
    ($this->decide)($winningBid, BidWorkflow::CONTRACT_SIGNED, [
        'performance_security_at' => now()->subDay()->toDateString(),
        'contract_date' => now()->toDateString(),
    ])->assertSessionHasErrors('performance_security_at');
    ($this->decide)($winningBid, BidWorkflow::CONTRACT_SIGNED, ['performance_security_at' => now()->toDateString()])->assertSessionHasErrors('contract_date');
    ($this->decide)($winningBid, BidWorkflow::CONTRACT_SIGNED, [
        'performance_security_at' => now()->toDateString(),
        'contract_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($award->fresh()->contract_date->isToday())->toBeTrue();
    // The signed NTP and its issuance date are required.
    ($this->decide)($winningBid, BidWorkflow::NOTICE_TO_PROCEED, ['ntp_issued_on' => now()->toDateString()])->assertSessionHasErrors('ntp_file');
    ($this->decide)($winningBid, BidWorkflow::NOTICE_TO_PROCEED, ['ntp_file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('ntp.pdf', "%PDF-1.4
1 0 obj
<<>>
endobj
trailer
<<>>
%%EOF"), 'ntp_issued_on' => now()->toDateString()])->assertSessionHasNoErrors();

    expect(($this->adminStage)($winningBid))->toBe('Notice to Proceed Issued');
    $track = ($this->trackFor)($winner);
    expect($track['current']['label'])->toBe('Notice to Proceed Issued')
        ->and(collect($track['stages'])->pluck('state')->unique()->values()->all())->toBe(['done'])
        ->and(collect($track['history'])->pluck('title')->all())->toContain('Technical Components Opened', 'Passed Preliminary Examination', 'Recommended for Award', 'Award Approved', 'Notice of Award Issued', 'Contract Signed', 'Notice to Proceed Issued');
});

it('records a HoPE disapproval with a reason as not awarded, not disqualified', function () {
    $bidder = ($this->makeBidder)('disapproved');
    $bid = ($this->makeBid)($bidder);
    ($this->openBids)();
    ($this->advanceToRecommended)($bid);

    ($this->decide)($bid, BidWorkflow::DISAPPROVE_AWARD)->assertSessionHasErrors('reason');
    ($this->decide)($bid, BidWorkflow::DISAPPROVE_AWARD, ['reason' => 'Recommended contract price exceeds available funds.'])
        ->assertSessionHasNoErrors();

    expect(($this->adminStage)($bid))->toBe('Not Awarded');
    $track = ($this->trackFor)($bidder);
    expect($track['outcome']['key'])->toBe('not_awarded')
        ->and(collect($track['history'])->firstWhere('title', 'Recommendation Not Approved')['reason'])->toBe('Recommended contract price exceeds available funds.');
});

it('issues the Notice of Award from the Awards page only for a HoPE-approved bid', function () {
    Storage::fake('local');
    $certificate = fn () => UploadedFile::fake()->createWithContent('certificate.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

    $bid = ($this->makeBid)(($this->makeBidder)('certificate'));
    ($this->openBids)();
    ($this->advanceToRecommended)($bid);

    testCase()->actingAs($this->admin)
        ->post(route('admin.awards.declare', $this->project), ['bid_id' => $bid->id, 'certificate_file' => $certificate()])
        ->assertSessionHasErrors('bid_id');

    ($this->decide)($bid, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors();

    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))
        ->assertSee('loadDeclareWinnerModal('.$this->project->id.', '.$bid->id.')', false);

    testCase()->actingAs($this->admin)
        ->post(route('admin.awards.declare', $this->project), ['bid_id' => $bid->id, 'certificate_file' => $certificate()])
        ->assertSessionHasNoErrors();

    // Same action as the Review modal: the signed NOA creates the award record.
    expect(Award::where('bid_id', $bid->id)->exists())->toBeTrue()
        ->and($bid->fresh()->notice_of_award_at)->not->toBeNull()
        ->and(BidTracking::where('bid_id', $bid->id)->where('decision', 'issued')->where('stage', BidProgress::STAGE_NOTICE_OF_AWARD)->exists())->toBeTrue();

    // One award per project.
    testCase()->actingAs($this->admin)
        ->post(route('admin.awards.declare', $this->project), ['bid_id' => $bid->id, 'certificate_file' => $certificate()])
        ->assertSessionHasErrors('bid_id');
    expect(Award::count())->toBe(1);
});

it('shows failed bidding on the admin table and the bidder track', function () {
    $bidder = ($this->makeBidder)('failed-bidding');
    $bid = ($this->makeBid)($bidder);
    ($this->openBids)();
    ($this->passPrelim)($bid);

    $rebid = Project::create([
        'title' => 'San Jose Farm-to-Market Road (Re-bid)',
        'description' => 'Second round.',
        'budget' => 1000000,
        'deadline' => now()->addDays(10),
        'status' => 'open',
        'award_criterion' => 'lowest_calculated_bid',
        'opening_documents_reference' => 'Signed bidding documents',
    ]);

    testCase()->actingAs($this->admin)
        ->post(route('admin.project.failed-bidding', $this->project), [
            'failed_bidding_reason' => 'All bids exceeded the approved budget for the contract.',
            'rebid_project_id' => $rebid->id,
        ])
        ->assertSessionHasNoErrors();

    expect(($this->adminStage)($bid))->toBe('Failed Bidding');
    $track = ($this->trackFor)($bidder);
    expect($track['current']['label'])->toBe('Failed Bidding')
        ->and($track['project_outcome']['rebid']['url'])->toBe(route('public.procurement.show', $rebid))
        ->and($track['outcome'])->toBeNull()
        ->and(collect($track['history'])->firstWhere('title', 'Failed Bidding Declared')['reason'])->toBe('All bids exceeded the approved budget for the contract.');

    ($this->decide)($bid, BidWorkflow::EVALUATE)->assertSessionHasErrors('milestone');
});

it('filters the admin table by current stage and maps old filter values', function () {
    ($this->openBids)();
    $submitted = ($this->makeBid)(($this->makeBidder)('f1'));
    $evaluating = ($this->makeBid)(($this->makeBidder)('f2'));
    ($this->passPrelim)($evaluating);

    $response = testCase()->actingAs($this->admin)->get(route('admin.bids', ['status' => BidProgress::STAGE_EVALUATION]))->assertOk();
    expect($response->viewData('bids')->pluck('id')->all())->toBe([$evaluating->id]);
    $response->assertSee('<option value="bid_evaluation" selected>Bid Evaluation</option>', false)
        ->assertDontSee('<option value="approved"', false);

    // Old bookmarked filter values keep working.
    expect(testCase()->get(route('admin.bids', ['status' => 'approved']))->viewData('bids')->pluck('id')->all())->toBe([$evaluating->id])
        ->and(testCase()->get(route('admin.bids', ['status' => 'pending']))->viewData('bids')->pluck('id')->all())->toBe([$submitted->id]);

    $rows = testCase()->get(route('admin.bids'))->viewData('exportRows');
    expect(collect($rows)->pluck('status_label')->sort()->values()->all())->toBe(['Bid Evaluation', 'Preliminary Examination']);
});

it('maps legacy pending, approved, rejected and awarded records without inventing milestones', function () {
    $legacy = fn (string $key, array $attributes) => ($this->makeBid)(($this->makeBidder)($key), $attributes);

    // Submitted five days ago, before the structured history existed.
    $this->travel(-5)->days();
    $pending = $legacy('legacy-pending', ['status' => 'pending']);
    $approved = $legacy('legacy-approved', ['status' => 'approved', 'workflow_step' => Bid::STEP_APPROVED]);
    $rejected = $legacy('legacy-rejected', ['status' => 'rejected']);
    $awarded = $legacy('legacy-awarded', ['status' => 'awarded', 'workflow_step' => Bid::STEP_AWARDED]);
    $this->travelBack();

    $approved->update(['approved_at' => now()->subDays(2), 'documents_validated_at' => now()->subDays(2)]);
    $awarded->update(['awarded_at' => now()->subDay()]);

    // Legacy timestamps remain in history, while a sealed financial bid stays at the evaluation gate.
    $this->project->update(['bids_opened_at' => now()->subDays(3)]);

    expect(($this->adminStage)($pending))->toBe('Preliminary Examination')
        ->and(($this->adminStage)($approved))->toBe('Bid Evaluation')
        ->and(($this->adminStage)($rejected))->toBe('Disqualified')
        ->and(($this->adminStage)($awarded))->toBe('Bid Evaluation');

    // History comes only from recorded timestamps.
    $history = BidHistory::for(Bid::find($awarded->id))->forAdmin();
    expect(collect($history)->pluck('title')->all())->toBe(['Bid Submitted', 'Bids Opened', 'Award Approved'])
        ->and(collect($history)->pluck('source')->unique()->all())->toBe(['record']);

    // A legacy rejection without a timestamp gets no invented disqualification event.
    expect(collect(BidHistory::for(Bid::find($rejected->id))->forAdmin())->pluck('title')->all())->toBe(['Bid Submitted', 'Bids Opened'])
        ->and(BidTracking::count())->toBe(0);
});

it('shows stage-appropriate actions instead of Approve and Reject in the Review Bid modal', function () {
    $bid = ($this->makeBid)(($this->makeBidder)('modal'));

    testCase()->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Submission sealed')
        ->assertSee('Technical and eligibility files open automatically')
        ->assertDontSee('bid-proposal-preview', false)
        ->assertDontSee('data-review-target="approve"', false);

    ($this->openBids)();

    testCase()->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Passed Preliminary Examination')
        ->assertSee('Failed Preliminary Examination')
        ->assertSee('data-br-action="pass_preliminary"', false)
        ->assertSee('Confirm each requirement was checked and complies (pass/fail)')
        ->assertSee('Remarks to share with the bidder')
        ->assertDontSee(BidWorkflow::label(BidWorkflow::NOTICE_OF_AWARD))
        ->assertDontSee('> Approve</button>', false)
        ->assertDontSee('Activity History');

    // Bulk approval no longer exists.
    testCase()->actingAs($this->admin)->post(route('admin.bids.bulk'), ['action' => 'approve', 'ids' => [$bid->id]])
        ->assertSessionHasErrors('action');
});

it("keeps BAC Staff read-only while preserving sealed access rules", function () {
    $staff = User::create(["name" => "BAC Staff", "email" => "staff-flow@example.com", "password" => Hash::make("password"), "role" => "staff", "status" => "active"]);
    Assignment::create(["staff_id" => $staff->id, "project_id" => $this->project->id, "role_in_project" => "Evaluator"]);
    $bidder = ($this->makeBidder)("staff-flow");
    $bid = ($this->makeBid)($bidder);

    testCase()->actingAs($staff)->get(route("staff.bids.proposal.preview", $bid))->assertForbidden();
    testCase()->actingAs($staff)->getJson(route("staff.bids.details", $bid))
        ->assertJsonPath("bid.sealed", true)
        ->assertJsonPath("bid.bid_amount", null)
        ->assertJsonPath("bid.status_label", "Submitted")
        ->assertJsonPath("bid.available_actions", []);

    ($this->openBids)();

    testCase()->actingAs($staff)->patchJson(route("staff.bids.validate", $bid), [
        "verified_requirements" => ["technical_proposal", "financial_proposal"],
    ])->assertForbidden();

    expect(BidTracking::where("bid_id", $bid->id)->where("decision", "passed")->count())->toBe(0);
});

it('only shows bidders their own bids', function () {
    $owner = ($this->makeBidder)('owner');
    $intruder = ($this->makeBidder)('intruder');
    $bid = ($this->makeBid)($owner);

    testCase()->actingAs($intruder)->get(route('bidder.bidding-track', ['bid' => $bid->id]))
        ->assertOk()
        ->assertDontSee('San Jose Farm-to-Market Road')
        ->assertSee('submitted any bids yet.');

    testCase()->actingAs($intruder)->getJson(route('bidder.bidding-track.data'))
        ->assertOk()
        ->assertJsonCount(0, 'bids');

    testCase()->actingAs($this->admin)->get(route('bidder.bidding-track'))->assertForbidden();
});

it('renders the bidder timeline with history and without the live-updating claim', function () {
    $bidder = ($this->makeBidder)('render');
    ($this->makeBid)($bidder);
    ($this->openBids)();

    testCase()->actingAs($bidder)->get(route('bidder.bidding-track'))
        ->assertOk()
        ->assertSee('bt-timeline', false)
        ->assertSee('Activity history')
        ->assertSee('Technical Components Opened')
        ->assertSee('Checks for updates every minute')
        ->assertDontSee('live Updating')
        ->assertDontSee('LIVE UPDATING');

    testCase()->actingAs($bidder)->getJson(route('bidder.bidding-track.data', ['html' => 1]))
        ->assertOk()
        ->assertJsonStructure(['bids' => [['id', 'signature', 'html', 'stages', 'current', 'history']], 'has_recent_updates']);
});
