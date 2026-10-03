<?php

use App\Models\Assignment;
use App\Models\Award;
use App\Models\Bid;
use App\Models\BiddingFeePayment;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Support\BidSubmissionRequirements;
use App\Support\BidWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * End-to-end: LGU San Jose, Occidental Mindoro competitive public bidding
 * (RA 9184 2016 IRR / PhilGEPS), from project creation to Notice to Proceed.
 */

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'flow-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'BAC Secretariat', 'email' => 'flow-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);
    $this->bidderA = User::create(['name' => 'Ana Reyes', 'email' => 'flow-a@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Builders']);
    $this->bidderB = User::create(['name' => 'Ben Cruz', 'email' => 'flow-b@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Occidental Construction']);

    $this->pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    $this->local = fn ($date) => $date->format('Y-m-d\TH:i');
    $this->recordPayment = fn (Project $project, User $bidder) => BiddingFeePayment::create([
        'project_id' => $project->id,
        'user_id' => $bidder->id,
        'amount' => $project->bidding_documents_fee,
        'or_number' => 'OR-'.$bidder->id,
        'status' => BiddingFeePayment::STATUS_VERIFIED,
        'paid_at' => now()->toDateString(),
        'verified_at' => now(),
        'recorded_by' => $this->admin->id,
    ]);

    $this->wizard = fn (array $overrides = []) => array_merge([
        'title' => 'Concreting of Brgy. Bubog Farm-to-Market Road',
        'description' => 'Concreting of 2.1 km farm-to-market road, Brgy. Bubog, San Jose, Occidental Mindoro.',
        'category' => 'infrastructure',
        'location' => 'Brgy. Bubog, San Jose, Occidental Mindoro',
        'procurement_mode' => 'public_bidding',
        'source_of_fund' => '20% Development Fund',
        'contract_duration' => '120 calendar days',
        'budget' => 150000000,
        'status' => 'open',
        'philgeps_reference_no' => '12005566',
        'end_user_unit' => 'Municipal Engineering Office',
        'date_posted' => now()->toDateString(),
        'pre_bid_conference_date' => ($this->local)(workdayAt(7, 10, 0)),
        'bid_submission_deadline' => ($this->local)(workdayAt(20, 9, 0)),
        'bid_opening_date' => ($this->local)(workdayAt(20, 9, 30)),
        'submission_mode' => 'electronic',
        'electronic_submission_authority' => 'BAC Resolution No. 2026-014',
        // Invitation to Bid details (RA 12009 IRR Sec. 50.2) and the pre-procurement
        // conference that this ABC requires before publication (Sec. 49.1).
        'award_criterion' => 'lowest_calculated_bid',
        'bid_opening_venue' => 'BAC Conference Room, Municipal Hall, San Jose',
        'pre_procurement_conference_at' => ($this->local)(now()->subDays(2)->setTime(10, 0)),
        'pre_procurement_reference' => 'Minutes of the Pre-Procurement Conference 2026-021',
        'document_type' => ['invitation_to_bid', 'bidding_documents'],
        'project_documents' => [($this->pdf)('ITB.pdf'), ($this->pdf)('PBD-Infrastructure.pdf')],
        'confirm_correct' => 'on',
    ], $overrides);

    $this->decide = fn (Bid $bid, string $action, array $extra = []) => testCase()->actingAs($this->admin)
        ->post(route('admin.bid.decision', $bid), array_merge(['action' => $action], $extra))
        ->assertSessionHasNoErrors();
});

it('keeps a project as a draft until the local BAC publication requirements are met', function () {
    testCase()->actingAs($this->admin)
        ->post(route('admin.projects.wizard.store'), ($this->wizard)([
            'philgeps_reference_no' => null,
            'pre_bid_conference_date' => null,
            'bid_opening_date' => ($this->local)(workdayAt(23, 9, 0)),
            'document_type' => ['bidding_documents'],
            'project_documents' => [($this->pdf)('PBD.pdf')],
        ]))
        ->assertRedirect(route('admin.projects').'?status=draft')
        ->assertSessionHas('error', fn (string $message) => ! str_contains($message, 'PhilGEPS reference number')
            && str_contains($message, 'Invitation to Bid')
            && str_contains($message, 'pre-bid conference')
            && str_contains($message, 'same day'));

    $project = Project::firstOrFail();
    expect($project->status)->toBe('draft')
        ->and($project->reference_no)->toBe('SJ-BAC-'.now()->format('Y').'-I-001');

    // Publishing is refused for the same reasons.
    testCase()->actingAs($this->admin)->postJson(route('admin.project.publish', $project))
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    // A posting period shorter than 7 calendar days is refused as well.
    $short = Project::create(['title' => 'Short Posting', 'description' => 'x', 'category' => 'goods', 'procurement_mode' => 'public_bidding', 'budget' => 500000, 'philgeps_reference_no' => '1', 'deadline' => now()->addDays(3)->setTime(9, 0), 'status' => 'draft']);
    $short->schedule()->create(['date_posted' => now()->toDateString(), 'bid_submission_deadline' => now()->addDays(3)->setTime(9, 0), 'bid_opening_date' => now()->addDays(3)->setTime(9, 30)]);
    expect($short->fresh()->publicationBlockers())->toHaveKey('date_posted');
});

it('publishes a forwarded purchase request locally and enforces the bidder deadline without PhilGEPS fields', function () {
    $request = ProcurementRequest::create([
        'reference_no' => 'PR-'.now()->format('Y').'-0099',
        'end_user_office' => 'Municipal Engineering Office',
        'requested_by' => $this->admin->id,
        'title' => 'Road rehabilitation request',
        'category' => 'infrastructure',
        'specifications' => 'Road rehabilitation works.',
        'quantity' => 1,
        'unit' => 'lot',
        'estimated_cost' => 1500000,
        'fund_source' => 'General Fund',
        'delivery_period' => '120 Calendar Days',
        'status' => ProcurementRequest::STATUS_FORWARDED,
        'forwarded_at' => now(),
        'reviewed_at' => now(),
    ]);

    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), ($this->wizard)([
        'status' => 'draft',
        'procurement_request_id' => $request->id,
        'philgeps_reference_no' => null,
        'philgeps_url' => null,
    ]))->assertRedirect(route('admin.projects').'?status=draft');

    $project = Project::firstOrFail();
    expect($project->status)->toBe('draft')
        ->and($project->procurement_request_id)->toBe($request->id)
        ->and($project->philgeps_reference_no)->toBeNull()
        ->and($request->fresh()->status)->toBe(ProcurementRequest::STATUS_IN_PROCUREMENT);

    testCase()->actingAs($this->admin)->postJson(route('admin.project.publish', $project))
        ->assertOk()
        ->assertJsonPath('success', true);

    $project->refresh();
    expect($project->status)->toBe('open')
        ->and($project->published_at)->not->toBeNull()
        ->and($project->philgeps_reference_no)->toBeNull()
        ->and($project->philgeps_url)->toBeNull();

    testCase()->actingAs($this->bidderA)->get(route('bidder.available-projects'))
        ->assertOk()
        ->assertSee($project->title);

    $files = collect(BidSubmissionRequirements::for($project)->requiredKeys())
        ->mapWithKeys(fn ($key) => [$key => ($this->pdf)($key.'.pdf')])
        ->all();

    $deadline = $project->bidSubmissionDeadline();
    ($this->recordPayment)($project, $this->bidderA);
    ($this->recordPayment)($project, $this->bidderB);
    $this->travelTo($deadline->copy()->subMinute());
    testCase()->actingAs($this->bidderA)->post(route('bidder.bids.store', $project), [
        'bid_amount' => '1400000',
        'documents' => $files,
        'financial_password' => '482913', 'financial_password_confirmation' => '482913',
    ])->assertSessionHasNoErrors();

    $this->travelTo($deadline->copy()->addMinute());
    testCase()->actingAs($this->bidderB)->post(route('bidder.bids.store', $project), [
        'bid_amount' => '1450000',
        'documents' => $files,
        'financial_password' => '482913', 'financial_password_confirmation' => '482913',
    ])->assertSessionHasErrors('deadline');
});
it('blocks failed bidding until the bids have been opened after the deadline', function () {
    $deadline = now()->subDay();
    $project = Project::create([
        'title' => 'No bids project',
        'description' => 'A project awaiting bid opening.',
        'category' => 'goods',
        'procurement_mode' => 'public_bidding',
        'budget' => 500000,
        'deadline' => $deadline,
        'status' => 'open',
    ]);
    ProjectSchedule::create([
        'project_id' => $project->id,
        'bid_submission_deadline' => $deadline,
        'bid_opening_date' => now()->addHour(),
    ]);

    testCase()->actingAs($this->admin)->post(route('admin.project.failed-bidding', $project), [
        'failed_bidding_reason' => 'No bids were received.',
    ])->assertSessionHasErrors('failed_bidding_reason');

    expect($project->fresh()->failed_bidding_at)->toBeNull();

    // At the scheduled opening the bids open by themselves; the BAC records that it conducted the opening.
    $this->travel(2)->hours();
    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project), [])->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.project.failed-bidding', $project), [
        'failed_bidding_reason' => 'No bids were received.',
    ])->assertSessionHasNoErrors();

    expect($project->fresh()->failed_bidding_at)->not->toBeNull();
});

it('does not open bids after failure of bidding has been declared', function () {
    $deadline = now()->subDay();
    $project = Project::create([
        'title' => 'Failed project',
        'description' => 'A failed bidding project.',
        'category' => 'goods',
        'procurement_mode' => 'public_bidding',
        'budget' => 500000,
        'deadline' => $deadline,
        'status' => 'closed',
        'failed_bidding_at' => now(),
        'failed_bidding_reason' => 'No bids were received.',
    ]);
    ProjectSchedule::create([
        'project_id' => $project->id,
        'bid_submission_deadline' => $deadline,
        'bid_opening_date' => $deadline->copy()->addHour(),
    ]);

    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project), [])->assertSessionHasErrors('bids_opened_at');

    expect($project->fresh()->bids_opened_at)->toBeNull();
});

it('runs the full LGU procurement flow from posting to Notice to Proceed', function () {
    // 1. Project creation and posting.
    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), ($this->wizard)())
        ->assertSessionHas('success', 'Project created successfully.');

    $project = Project::firstOrFail();
    expect($project->status)->toBe('open')
        ->and($project->reference_no)->toBe('SJ-BAC-'.now()->format('Y').'-I-001')
        ->and($project->submission_mode)->toBe(Project::SUBMISSION_ELECTRONIC)
        ->and($project->electronic_submission_authority)->toBe('BAC Resolution No. 2026-014');
    Assignment::create(['staff_id' => $this->staff->id, 'project_id' => $project->id, 'role_in_project' => 'BAC Secretariat']);
    testCase()->actingAs($this->admin)->post(route('admin.project.bid-opening-rules', $project), [
        'award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'PBD-Infrastructure.pdf, Section III',
    ])->assertSessionHasNoErrors();

    // Status shortcuts are not allowed.
    testCase()->actingAs($this->admin)->putJson(route('admin.project.update', $project), [
        'title' => $project->title, 'description' => $project->description, 'budget' => 150000000,
        'status' => 'awarded', 'deadline' => $project->deadline->toDateTimeString(),
    ])->assertStatus(422);
    testCase()->actingAs($this->staff)->patch(route('staff.projects.status', $project), ['status' => 'closed'])
        ->assertSessionHasErrors('status');
    expect($project->fresh()->status)->toBe('open');

    // 2. Bidding: both bidders see the notice and submit electronically.
    ($this->recordPayment)($project, $this->bidderA);
    ($this->recordPayment)($project, $this->bidderB);
    testCase()->actingAs($this->bidderA)->get(route('bidder.available-projects'))
        ->assertSee('SJ-BAC-'.now()->format('Y').'-I-001')
        ->assertSee('12005566')
        ->assertSee('ITB.pdf');

    $files = collect(BidSubmissionRequirements::for($project)->requiredKeys())->mapWithKeys(fn ($key) => [$key => ($this->pdf)($key.'.pdf')])->all();
    testCase()->actingAs($this->bidderA)->post(route('bidder.bids.store', $project), ['project_id' => $project->id, 'bid_amount' => '149,500,000.00', 'documents' => $files, 'financial_password' => '482913', 'financial_password_confirmation' => '482913'])
        ->assertSessionHasNoErrors();
    $files = collect(BidSubmissionRequirements::for($project)->requiredKeys())->mapWithKeys(fn ($key) => [$key => ($this->pdf)($key.'.pdf')])->all();
    testCase()->actingAs($this->bidderB)->post(route('bidder.bids.store', $project), ['project_id' => $project->id, 'bid_amount' => '149,900,000', 'documents' => $files, 'financial_password' => '482913', 'financial_password_confirmation' => '482913'])
        ->assertSessionHasNoErrors();

    $bidA = Bid::where('user_id', $this->bidderA->id)->firstOrFail();
    $bidB = Bid::where('user_id', $this->bidderB->id)->firstOrFail();
    expect($bidA->receipt_no)->not->toBeNull()->and($bidA->progress()->adminStatus()['label'])->toBe('Submitted');

    // 3. Bid opening: not before the deadline and scheduled opening; recorded by the BAC Secretariat.
    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project))->assertSessionHasErrors('bids_opened_at');
    $this->travelTo(workdayAt(20, 9, 45));
    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project))->assertSessionHasNoErrors();
    expect($project->fresh()->status)->toBe('closed');

    // 4. Preliminary examination, evaluation, post-qualification of the LCB.
    $keys = BidSubmissionRequirements::for($project)->requiredKeys();
    foreach ([$bidA, $bidB] as $bid) {
        ($this->decide)($bid, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => $keys]);
        testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $bid), ['opening_password' => '482913'])->assertSessionHasNoErrors();
        ($this->decide)($bid, BidWorkflow::START_EVALUATION);
        ($this->decide)($bid, BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the configured project criteria.']);
    }
    ($this->decide)($bidA, BidWorkflow::START_POST_QUALIFICATION);
    ($this->decide)($bidA, BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Post-qualification documents and qualifications verified.']);

    // 5. BAC resolution recommending award; HoPE (Municipal Mayor) approval.
    ($this->decide)($bidA, BidWorkflow::RECOMMEND, ['bac_resolution_no' => 'BAC Resolution No. 2026-031', 'bac_resolution_date' => now()->toDateString()]);
    ($this->decide)($bidA, BidWorkflow::APPROVE_AWARD);
    expect($bidB->fresh()->progress()->adminStatus()['label'])->toBe('Not Awarded');

    // 6. Notice of Award from the Awards page (signed NOA PDF).
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))
        ->assertSee('Notice of Award pending');
    testCase()->actingAs($this->admin)->post(route('admin.awards.declare', $project), ['bid_id' => $bidA->id, 'certificate_file' => ($this->pdf)('NOA-signed.pdf')])
        ->assertSessionHasNoErrors();

    $award = Award::where('bid_id', $bidA->id)->firstOrFail();
    expect($project->fresh()->status)->toBe('awarded')
        ->and($award->contract_amount)->toBe('149500000.00')
        ->and($award->notice_of_award_date->isToday())->toBeTrue()
        ->and($award->contract_date)->toBeNull();

    testCase()->actingAs($this->bidderA)->get(route('bidder.awarded-contracts'))
        ->assertSee('Contract signing pending');
    testCase()->get(route('public.awards'))->assertSee('Concreting of Brgy. Bubog Farm-to-Market Road');

    // 7. Contract signing (performance security) and Notice to Proceed.
    ($this->decide)($bidA, BidWorkflow::CONTRACT_SIGNED, ['performance_security_at' => now()->toDateString(), 'contract_date' => now()->toDateString()]);
    ($this->decide)($bidA, BidWorkflow::NOTICE_TO_PROCEED);

    expect($award->fresh()->contract_date->isToday())->toBeTrue();

    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertSee('NTP issued');
    testCase()->actingAs($this->bidderA)->get(route('bidder.awarded-contracts'))->assertSee('NTP issued');

    $trackA = testCase()->actingAs($this->bidderA)->getJson(route('bidder.bidding-track.data'))->json('bids.0');
    expect($trackA['current']['label'])->toBe('Notice to Proceed Issued')
        ->and($trackA['outcome']['key'])->toBe('awarded');

    $trackB = testCase()->actingAs($this->bidderB)->getJson(route('bidder.bidding-track.data'))->json('bids.0');
    expect($trackB['outcome']['key'])->toBe('not_awarded');
});

it('schedules the deadline, bid opening and pre-bid conference within LGU office hours', function () {
    $saturday = now()->addDays(20)->next(\Carbon\CarbonInterface::SATURDAY)->setTime(9, 0);

    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), ($this->wizard)([
        'bid_submission_deadline' => ($this->local)($saturday),
        'bid_opening_date' => ($this->local)($saturday->copy()->setTime(9, 30)),
    ]))->assertSessionHas('error', fn (string $message) => str_contains($message, 'working day (Monday to Friday), between 8:00 AM and 5:00 PM'));

    $evening = workdayAt(20, 19, 0);
    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), ($this->wizard)([
        'title' => 'Evening deadline',
        'bid_submission_deadline' => ($this->local)($evening),
        'bid_opening_date' => ($this->local)($evening->copy()->setTime(19, 30)),
    ]));

    expect(Project::where('status', 'open')->count())->toBe(0)
        ->and(Project::where('title', 'Evening deadline')->firstOrFail()->publicationBlockers())->toHaveKey('bid_submission_deadline');
});

it('closes bidding at the Philippine time typed in the wizard', function () {
    expect(config('app.timezone'))->toBe('Asia/Manila');

    $deadline = workdayAt(20, 9, 0);
    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), ($this->wizard)());
    $project = Project::firstOrFail();
    expect($project->deadline->format('Y-m-d H:i'))->toBe($deadline->format('Y-m-d H:i'));
    ($this->recordPayment)($project, $this->bidderA);
    ($this->recordPayment)($project, $this->bidderB);

    $files = fn () => collect(BidSubmissionRequirements::for($project)->requiredKeys())->mapWithKeys(fn ($key) => [$key => ($this->pdf)($key.'.pdf')])->all();

    // 8:59 AM PST: still accepted.
    $this->travelTo($deadline->copy()->subMinute());
    testCase()->actingAs($this->bidderA)->post(route('bidder.bids.store', $project), ['project_id' => $project->id, 'bid_amount' => '140000000', 'documents' => $files(), 'financial_password' => '482913', 'financial_password_confirmation' => '482913'])
        ->assertSessionHasNoErrors();

    // 9:01 AM PST: late (previously accepted until 5:00 PM because of UTC).
    $this->travelTo($deadline->copy()->addMinute());
    testCase()->actingAs($this->bidderB)->post(route('bidder.bids.store', $project), ['project_id' => $project->id, 'bid_amount' => '140000000', 'documents' => $files(), 'financial_password' => '482913', 'financial_password_confirmation' => '482913'])
        ->assertSessionHasErrors('deadline');

    expect(Bid::where('user_id', $this->bidderA->id)->firstOrFail()->submitted_at->format('Y-m-d H:i'))->toBe($deadline->copy()->subMinute()->format('Y-m-d H:i'));
});

it('lets the BAC correct a draft schedule in the edit form and then publish it explicitly', function () {
    $saturday = now()->addDays(20)->next(\Carbon\CarbonInterface::SATURDAY)->setTime(10, 0);

    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), ($this->wizard)([
        'budget' => 1500000,
        'status' => 'draft',
        // An RA 9184 project: the pre-bid conference is required from PHP1M.
        'legal_basis' => 'ra_9184',
        'date_posted' => null,
        'pre_bid_conference_date' => null,
        'bid_submission_deadline' => ($this->local)($saturday),
        'bid_opening_date' => ($this->local)($saturday->copy()->setTime(10, 30)),
    ]));
    $project = Project::firstOrFail();
    expect($project->status)->toBe('draft')
        ->and($project->schedule->date_posted)->toBeNull();

    $deadline = workdayAt(20, 10, 0);
    $edit = fn (array $data) => testCase()->actingAs($this->admin)->putJson(route('admin.project.update', $project), array_merge([
        'title' => $project->title,
        'description' => $project->description,
        'budget' => 1500000,
        'status' => 'draft',
        'deadline' => ($this->local)($deadline),
        'bid_opening_date' => ($this->local)($deadline->copy()->setTime(10, 30)),
    ], $data));

    // Save Changes updates the draft but does not publish it.
    $edit([])->assertOk();
    expect($project->fresh()->status)->toBe('draft');

    // Publish reports the legal blocker at field level; Date Posted is not a blocker.
    testCase()->actingAs($this->admin)
        ->postJson(route('admin.project.publish', $project))
        ->assertStatus(422)
        ->assertJsonPath('errors.pre_bid_conference_date.0', fn ($message) => str_contains($message, 'pre-bid conference'))
        ->assertJsonMissingPath('errors.date_posted');

    $edit(['pre_bid_conference_date' => ($this->local)(workdayAt(7, 10, 0))])->assertOk();

    testCase()->actingAs($this->admin)
        ->postJson(route('admin.project.publish', $project))
        ->assertOk()
        ->assertJsonPath('success', true);

    $project->refresh()->load('schedule');
    expect($project->status)->toBe('open')
        ->and($project->published_at)->not->toBeNull()
        ->and($project->published_at->timezone('Asia/Manila')->format('Y-m-d'))->toBe(now('Asia/Manila')->format('Y-m-d'))
        ->and($project->schedule->date_posted->format('Y-m-d'))->toBe(now('Asia/Manila')->format('Y-m-d'))
        ->and($project->deadline->format('Y-m-d H:i'))->toBe($deadline->format('Y-m-d H:i'))
        ->and($project->schedule->bid_submission_deadline->format('Y-m-d H:i'))->toBe($deadline->format('Y-m-d H:i'));

    testCase()->actingAs($this->bidderA)
        ->get(route('bidder.available-projects'))
        ->assertOk()
        ->assertSee($project->title);
});

it('uses the RA 12009 pre-bid threshold when publishing a 1.5M draft', function () {
    $deadline = workdayAt(10, 10, 0);
    $project = Project::create([
        'title' => 'RA 12009 Office Supply Draft',
        'description' => 'Supply and delivery of office equipment.',
        'category' => 'goods',
        'procurement_mode' => 'public_bidding',
        'legal_basis' => 'ra_12009',
        'award_criterion' => 'lowest_calculated_bid',
        'bid_opening_venue' => 'BAC Conference Room, Municipal Hall',
        'electronic_submission_authority' => 'MIS Certification No. 2026-03',
        'budget' => 1500000,
        'deadline' => $deadline,
        'status' => 'draft',
        'philgeps_reference_no' => null,
        'philgeps_url' => null,
    ]);
    $project->schedule()->create([
        'date_posted' => null,
        'bid_submission_deadline' => $deadline,
        'bid_opening_date' => $deadline->copy()->setTime(10, 30),
    ]);
    $project->documents()->create([
        'original_name' => 'itb.pdf',
        'file_path' => 'project-documents/itb.pdf',
        'document_type' => 'invitation_to_bid',
    ]);

    testCase()->actingAs($this->admin)
        ->get(route('admin.project.edit', $project))
        ->assertOk()
        ->assertSee('Pre-bid conference')
        ->assertDontSee('Required if ABC', false);

    testCase()->actingAs($this->admin)
        ->postJson(route('admin.project.publish', $project))
        ->assertOk()
        ->assertJsonPath('success', true);

    $project->refresh();
    expect($project->status)->toBe('open')
        ->and($project->published_at)->not->toBeNull()
        ->and($project->philgeps_reference_no)->toBeNull()
        ->and($project->philgeps_url)->toBeNull();
});
