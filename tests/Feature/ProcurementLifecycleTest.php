<?php

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BiddingFeePayment;
use App\Models\Assignment;
use App\Models\BidTracking;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\ProjectProceeding;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\BidWorkflow;
use App\Support\ProcurementTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');

    $user = fn (string $email, string $role, array $extra = []) => User::create(array_merge([
        'name' => ucfirst(str_replace('_', ' ', $role)).' User',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => $role,
        'status' => 'active',
    ], $extra));

    $this->admin = $user('lc-admin@example.com', 'admin');
    $this->staff = $user('lc-staff@example.com', 'staff', ['office' => 'Budget Office']);
    $this->engineering = $user('lc-meo@example.com', 'end_user', ['name' => 'Engr. Reyes', 'office' => 'Municipal Engineering Office']);
    $this->health = $user('lc-mho@example.com', 'end_user', ['office' => 'Municipal Health Office']);
    $this->bidder = $user('lc-bidder@example.com', 'bidder', ['company' => 'Mindoro Builders']);

    $this->requestData = [
        'title' => 'Supply and delivery of office equipment',
        'category' => 'goods',
        'specifications' => "10 units desktop computers, Core i5, 16GB RAM\n5 units laser printers",
        'quantity' => '1',
        'unit' => 'lot',
        'estimated_cost' => '1450000',
        'fund_source' => 'General Fund',
        'delivery_period' => '30 calendar days from receipt of NTP',
        'justification' => 'Replacement of units beyond repair.',
    ];

    $this->pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

    $this->openProject = function (array $attributes = []): Project {
        $project = Project::create(array_merge([
            'title' => 'Concreting of Barangay Road',
            'description' => 'Road concreting works.',
            'reference_no' => 'SJOM-2026-I-014',
            'philgeps_reference_no' => '11410616',
            'category' => 'infrastructure',
            'procurement_mode' => 'public_bidding',
            'legal_basis' => 'ra_12009',
            'budget' => 2500000,
            'deadline' => now()->addDays(5),
            'status' => 'open',
        ], $attributes));
        ProjectSchedule::create([
            'project_id' => $project->id,
            'date_posted' => now()->subDays(3)->toDateString(),
            'bid_submission_deadline' => $project->deadline,
            'bid_opening_date' => $project->deadline->copy()->addHour(),
        ]);
        Assignment::create(['staff_id' => $this->staff->id, 'project_id' => $project->id, 'role_in_project' => 'BAC Secretariat']);

        return $project->fresh();
    };
});

it('denies unassigned staff access to procurement lifecycle records', function () {
    $project = ($this->openProject)();
    Assignment::where('staff_id', $this->staff->id)->where('project_id', $project->id)->delete();

    testCase()->actingAs($this->staff)->get(route('staff.procurement.show', $project))->assertForbidden();
    testCase()->actingAs($this->staff)->post(route('staff.procurement.publication', $project), [
        'philgeps_reference_no' => '12345',
        'philgeps_posted_at' => now()->toDateString(),
    ])->assertForbidden();
});

it('takes a request from the end-user office through PPMP/APP review to a BAC project', function () {
    // The end-user office files and submits the request, with its TOR.
    testCase()->actingAs($this->engineering)->get(route('end-user.requests.create'))->assertOk()->assertSee('New purchase request');

    testCase()->actingAs($this->engineering)->post(route('end-user.requests.store'), $this->requestData + [
        'action' => 'submit',
        'documents' => [($this->pdf)('TOR.pdf')],
        'document_types' => ['tor'],
    ])->assertSessionHasNoErrors();

    $request = ProcurementRequest::with('documents')->firstOrFail();
    expect($request->reference_no)->toBe('PR-'.now()->format('Y').'-0001')
        ->and($request->status)->toBe(ProcurementRequest::STATUS_SUBMITTED)
        ->and($request->end_user_office)->toBe('Municipal Engineering Office')
        ->and($request->requested_by)->toBe($this->engineering->id)
        ->and($request->documents)->toHaveCount(1)
        ->and($request->documents->first()->document_type)->toBe('tor')
        ->and(UserNotification::where('user_id', $this->staff->id)->where('type', 'procurement_request')->exists())->toBeTrue();

    // Staff return it with remarks; forwarding needs the PPMP/APP references and the budget.
    testCase()->actingAs($this->staff)->get(route('staff.requests'))->assertOk()->assertSee($request->reference_no)->assertSee('Municipal Engineering Office');

    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), ['decision' => 'return'])
        ->assertSessionHasErrors('review_remarks');
    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), ['decision' => 'forward', 'budget_available' => '1'])
        ->assertSessionHasErrors(['ppmp_reference', 'app_reference']);
    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), ['decision' => 'forward', 'ppmp_reference' => 'PPMP-MEO-2026-12', 'app_reference' => 'APP-2026-45', 'budget_available' => '0'])
        ->assertSessionHasErrors('budget_available');

    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), ['decision' => 'return', 'review_remarks' => 'Attach the itemized specifications of the printers.'])
        ->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe(ProcurementRequest::STATUS_RETURNED)
        ->and($request->fresh()->budget_available)->toBeFalse()
        ->and($request->fresh()->budget_confirmed_by)->toBeNull();

    testCase()->actingAs($this->engineering)->get(route('end-user.dashboard'))->assertOk()
        ->assertSee('Needs your action')
        ->assertSee('Attach the itemized specifications of the printers.');

    // The office corrects and resubmits.
    testCase()->actingAs($this->engineering)->put(route('end-user.requests.update', $request), array_merge($this->requestData, [
        'specifications' => $this->requestData['specifications']."\nPrinters: monochrome, 40 ppm, duplex",
        'action' => 'submit',
    ]))->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe(ProcurementRequest::STATUS_SUBMITTED);

    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), [
        'decision' => 'forward',
        'ppmp_reference' => 'PPMP-MEO-2026-12',
        'app_reference' => 'APP-2026-45',
        'budget_available' => '1',
    ])->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(ProcurementRequest::STATUS_FORWARDED)
        ->and($request->forwarded_at)->not->toBeNull()
        ->and($request->reviewed_by)->toBe($this->staff->id)
        ->and($request->isEditable())->toBeFalse()
        // The budget confirmation records who ticked it and when.
        ->and($request->budget_available)->toBeTrue()
        ->and($request->budget_confirmed_by)->toBe($this->staff->id)
        ->and($request->budget_confirmed_at)->not->toBeNull();
    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']))->assertOk()
        ->assertSee('Budget confirmed')
        ->assertSee($this->staff->name);

    // The BAC starts the bidding project from the request: the wizard is prefilled.
    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']))->assertOk()->assertSee('Prepare procurement');
    testCase()->actingAs($this->admin)->get(route('admin.projects.create', ['request' => $request->id]))->assertOk()
        ->assertSee('Preparing bidding from '.$request->reference_no)
        ->assertSee('value="'.$request->id.'"', false)
        ->assertSee('Supply and delivery of office equipment');

    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), [
        'status' => 'draft',
        'procurement_request_id' => $request->id,
        'title' => $request->title,
        'description' => $request->specifications,
        'category' => 'goods',
        'budget' => '1450000',
        'end_user_unit' => $request->end_user_office,
        'submission_mode' => 'manual',
        'legal_basis' => 'ra_12009',
        'bid_security_required' => '1',
        'bid_security_notes' => 'In the amount stated in ITB Clause 16.',
    ])->assertSessionHasNoErrors();

    $project = Project::firstOrFail();
    expect($project->procurement_request_id)->toBe($request->id)
        ->and($project->submission_mode)->toBe(Project::SUBMISSION_MANUAL)
        ->and($project->bid_security_required)->toBeTrue()
        ->and($request->fresh()->status)->toBe(ProcurementRequest::STATUS_IN_PROCUREMENT);

    // A request can back only one project.
    testCase()->actingAs($this->admin)->get(route('admin.projects.create', ['request' => $request->id]))->assertNotFound();

    // The office follows the project from its request.
    testCase()->actingAs($this->engineering)->get(route('end-user.requests.show', $request))->assertOk()
        ->assertSee('Procurement progress')
        ->assertSee('Bidding documents, ABC &amp; schedule', false)
        ->assertSee('PPMP-MEO-2026-12');

    // Returned once, forwarded once; refused attempts are not decisions.
    expect(AuditLog::where('action', 'procurement_request_reviewed')->count())->toBe(2)
        ->and(AuditLog::where('action', 'procurement_request_converted')->exists())->toBeTrue();
});

it('keeps each office to its own requests and each role to its own pages', function () {
    testCase()->actingAs($this->engineering)->post(route('end-user.requests.store'), $this->requestData + ['action' => 'draft'])->assertSessionHasNoErrors();
    $request = ProcurementRequest::firstOrFail();

    expect($request->status)->toBe(ProcurementRequest::STATUS_DRAFT);

    testCase()->actingAs($this->health)->get(route('end-user.requests.show', $request))->assertNotFound();
    testCase()->actingAs($this->health)->get(route('end-user.requests.index'))->assertOk()->assertDontSee($request->title);

    testCase()->actingAs($this->bidder)->get(route('end-user.dashboard'))->assertForbidden();
    testCase()->actingAs($this->engineering)->get(route('admin.requests'))->assertForbidden();
    testCase()->actingAs($this->engineering)->get(route('staff.requests'))->assertForbidden();

    // Drafts are invisible to reviewers and cannot be reviewed.
    testCase()->actingAs($this->staff)->get(route('staff.requests', ['tab' => 'all']))->assertDontSee($request->reference_no);
    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), ['decision' => 'forward', 'ppmp_reference' => 'x', 'app_reference' => 'y', 'budget_available' => '1'])
        ->assertSessionHasErrors('decision');

    // Request files are only for the office and the BAC.
    testCase()->actingAs($this->engineering)->put(route('end-user.requests.update', $request), $this->requestData + [
        'documents' => [($this->pdf)('specs.pdf')],
        'document_types' => ['specifications'],
    ])->assertSessionHasNoErrors();
    $document = $request->documents()->firstOrFail();

    testCase()->actingAs($this->engineering)->get(route('procurement.files.request', $document))->assertOk();
    testCase()->actingAs($this->staff)->get(route('procurement.files.request', $document))->assertOk();
    testCase()->actingAs($this->health)->get(route('procurement.files.request', $document))->assertForbidden();
    testCase()->actingAs($this->bidder)->get(route('procurement.files.request', $document))->assertForbidden();
});

it('logs end-user office accounts into their dashboard and lets the admin create them', function () {
    // End-user offices confirm the sign-in with the emailed code.
    \Illuminate\Support\Facades\Mail::fake();
    testCase()->postJson(route('login'), ['email' => 'lc-meo@example.com', 'password' => 'password'])->assertOk()->assertJsonPath('requires_verification', true);
    $loginCode = null;
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\LoginVerificationCodeMail::class, function ($mail) use (&$loginCode) { $loginCode = $mail->code; return true; });
    testCase()->postJson(route('login.verify-code'), ['code' => $loginCode])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('redirect', route('end-user.dashboard'));

    testCase()->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'MSWDO Account', 'email' => 'mswdo@example.com', 'role' => 'end_user', 'status' => 'active',
        'password' => 'secret123', 'office' => 'SJBAC',
    ])->assertSessionHasErrors('office');

    testCase()->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'MSWDO Account', 'email' => 'mswdo@example.com', 'role' => 'end_user', 'status' => 'active',
        'password' => 'secret123', 'office' => 'Municipal Social Welfare and Development Office',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'mswdo@example.com')->value('office'))->toBe('Municipal Social Welfare and Development Office');
});

it('records the PhilGEPS posting and BAC proceedings with date checks and documents', function () {
    // Bid bulletins are issued at least 7 calendar days before the deadline (RA 12009 IRR Sec. 51.5).
    $project = ($this->openProject)(['deadline' => now()->addDays(10)]);
    $show = fn (User $user) => testCase()->actingAs($user)->get(route($user->role.'.procurement.show', $project));

    $show($this->admin)->assertOk()
        ->assertSee('Progress timeline')
        ->assertSee('PhilGEPS posting not recorded')
        ->assertSee('This system does not post to PhilGEPS');
    $show($this->staff)->assertOk()->assertSee('Next required action');

    $publication = fn (array $data) => testCase()->actingAs($this->staff)->post(route('staff.procurement.publication', $project), $data);
    $publication(['philgeps_reference_no' => '11410616', 'philgeps_posted_at' => now()->addDay()->toDateString()])->assertSessionHasErrors('philgeps_posted_at');
    $publication(['philgeps_reference_no' => '11410616', 'philgeps_posted_at' => now()->subDays(3)->toDateString(), 'philgeps_url' => 'not a link'])->assertSessionHasErrors('philgeps_url');
    $publication([
        'philgeps_reference_no' => '11410616',
        'philgeps_posted_at' => now()->subDays(3)->toDateString(),
        'philgeps_url' => 'https://notices.philgeps.gov.ph/GEPSNONPILOT/Tender/PrintableBidNoticeAbstractUI.aspx?refid=11410616',
    ])->assertSessionHasNoErrors();

    $project->refresh();
    expect($project->philgeps_posted_at->toDateString())->toBe(now()->subDays(3)->toDateString())
        ->and($project->philgeps_posted_recorded_by)->toBe($this->staff->id);
    $show($this->admin)->assertDontSee('PhilGEPS posting not recorded')->assertSee('View on PhilGEPS');

    $record = fn (array $data) => testCase()->actingAs($this->admin)->post(route('admin.procurement.proceedings', $project), $data);
    $local = fn ($date) => $date->copy()->timezone(config('bac-office.display_timezone'))->format('Y-m-d\TH:i');

    // A bulletin needs its number; nothing can be dated in the future.
    $record(['type' => 'bid_bulletin', 'occurred_at' => $local(now()->subHour())])->assertSessionHasErrors('reference_no');
    $record(['type' => 'pre_bid_conference', 'occurred_at' => $local(now()->addHour())])->assertSessionHasErrors('occurred_at');
    // A request for reconsideration comes only after the bid opening.
    $record(['type' => 'request_for_reconsideration', 'occurred_at' => $local(now()->subHour())])->assertSessionHasErrors('type');

    $record([
        'type' => 'bid_bulletin',
        'occurred_at' => $local(now()->subHour()),
        'reference_no' => 'Supplemental Bid Bulletin No. 1',
        'summary' => 'Clarified the delivery schedule.',
        'document' => ($this->pdf)('bulletin-1.pdf'),
    ])->assertSessionHasNoErrors();

    $bulletin = ProjectProceeding::firstOrFail();
    expect($bulletin->file_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($bulletin->file_path))->toBeTrue();

    $show($this->admin)->assertSee('Supplemental / Bid Bulletin')->assertSee('bulletin-1.pdf');
    testCase()->actingAs($this->staff)->get(route('procurement.files.proceeding', $bulletin))->assertOk();
    testCase()->actingAs($this->bidder)->get(route('procurement.files.proceeding', $bulletin))->assertForbidden();

    // After the deadline, pre-bid records are refused.
    $this->travel(11)->days();
    $record(['type' => 'clarification', 'occurred_at' => $local(now()->subHour())])->assertSessionHasErrors('occurred_at');

    // Draft projects are not posted yet.
    $draft = ($this->openProject)(['title' => 'Draft Project', 'reference_no' => 'SJOM-2026-G-002', 'status' => 'draft', 'deadline' => now()->addDays(20)]);
    testCase()->actingAs($this->admin)->post(route('admin.procurement.publication', $draft), ['philgeps_reference_no' => '1', 'philgeps_posted_at' => now()->toDateString()])
        ->assertSessionHasErrors('philgeps_posted_at');
});

it('requires a BAC resolution to recommend the award and keeps supporting documents', function () {
    $project = ($this->openProject)();
    $bid = Bid::create([
        'project_id' => $project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 2400000,
        'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED,
        'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now(), 'receipt_no' => 'BAC-2026-0001-E00001',
    ]);
    $this->travel(6)->days();
    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project))->assertSessionHasNoErrors();

    $decide = fn (string $action, array $extra = []) => testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $bid), ['action' => $action] + $extra);
    $decide(BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => $bid->fresh()->documentChecklist() ? collect($bid->fresh()->documentChecklist())->pluck('key')->all() : []]);
    // The bid has no uploaded files in this test, so pass it the way legacy bids pass.
    // Evaluation needs the authorized financial opening recorded too.
    Bid::whereKey($bid->id)->update([
        'workflow_step' => Bid::STEP_DOCUMENTS_VALIDATED, 'documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id,
        'eligibility_status' => Bid::ELIGIBILITY_VALID, 'financial_opened_at' => now(), 'financial_opened_by' => $this->admin->id,
    ]);

    $decide(BidWorkflow::START_EVALUATION)->assertSessionHasNoErrors();
    $decide(BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the configured project criteria.', 'supporting_document' => ($this->pdf)('evaluation-report.pdf')])->assertSessionHasNoErrors();
    $decide(BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
    $decide(BidWorkflow::PASS_POST_QUALIFICATION, ['supporting_document' => UploadedFile::fake()->create('report.exe', 10)])->assertSessionHasErrors('supporting_document');
    $decide(BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Post-qualification documents and qualifications verified.'])->assertSessionHasNoErrors();

    $decide(BidWorkflow::RECOMMEND)->assertSessionHasErrors('bac_resolution_no');
    $decide(BidWorkflow::RECOMMEND, ['bac_resolution_no' => '2026-031', 'bac_resolution_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('bac_resolution_date');
    $decide(BidWorkflow::RECOMMEND, [
        'bac_resolution_no' => 'BAC Resolution No. 2026-031',
        'bac_resolution_date' => now()->toDateString(),
        'supporting_document' => ($this->pdf)('resolution-2026-031.pdf'),
    ])->assertSessionHasNoErrors();

    $bid->refresh();
    expect($bid->bac_resolution_no)->toBe('BAC Resolution No. 2026-031')
        ->and($bid->bac_resolution_date->toDateString())->toBe(now()->toDateString());

    $tracking = BidTracking::where('bid_id', $bid->id)->where('decision', 'recommended')->firstOrFail();
    expect($tracking->attachment_name)->toBe('resolution-2026-031.pdf')
        ->and($tracking->details['bac_resolution_no'])->toBe('BAC Resolution No. 2026-031');

    testCase()->actingAs($this->admin)->get(route('admin.procurement.show', $project))->assertOk()
        ->assertSee('Resolution No. BAC Resolution No. 2026-031')
        ->assertSee('resolution-2026-031.pdf')
        ->assertSee('evaluation-report.pdf');
    testCase()->actingAs($this->admin)->get(route('procurement.files.decision', $tracking))->assertOk();
    testCase()->actingAs($this->bidder)->get(route('procurement.files.decision', $tracking))->assertForbidden();

    // The HoPE (admin) still decides; there is no automatic winner.
    $timeline = ProcurementTimeline::forProject($project->fresh());
    expect($timeline->current()['key'])->toBe('award')
        ->and($timeline->nextAction()['office'])->toBe('Head of the Procuring Entity');
});

it('receives manual sealed bids only on manual projects and only after the fee is paid', function () {
    $project = ($this->openProject)(['submission_mode' => Project::SUBMISSION_MANUAL, 'bidding_documents_fee' => 5000]);

    // Told up front that the sealed envelopes are the bid, and that the fee comes first; nothing is filed online.
    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertSee('This project takes sealed paper bids only')
        ->assertSee('Pay the bidding documents fee');
    testCase()->actingAs($this->bidder)->post(route('bidder.bids.store', $project), ['project_id' => $project->id, 'bid_amount' => '2400000'])
        ->assertSessionHasErrors('submission');
    expect(Bid::count())->toBe(0);

    // A draft kept from before (the sealed bid is what counts) is received only after payment.
    $bid = Bid::create([
        'project_id' => $project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 2400000,
        'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_MANUAL,
    ]);
    expect($bid->isDraft())->toBeTrue()->and($bid->submission_channel)->toBe(Bid::CHANNEL_MANUAL);

    $receive = fn () => testCase()->actingAs($this->staff)->post(route('admin.bid.decision', $bid), []);
    $local = now()->subHour()->timezone(config('bac-office.display_timezone'))->format('Y-m-d\TH:i');
    $record = fn () => testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $bid), [
        'action' => BidWorkflow::RECORD_MANUAL_RECEIPT, 'receipt_no' => 'LOG-2026-031', 'received_at' => $local,
    ]);

    // The fee is recorded first.
    $record()->assertSessionHasErrors('milestone');
    // A pending payment does not count; the BAC-verified one with its OR does.
    $payment = BiddingFeePayment::create(['project_id' => $project->id, 'user_id' => $this->bidder->id, 'amount' => 5000, 'or_number' => 'OR-1', 'status' => BiddingFeePayment::STATUS_PENDING, 'paid_at' => now()->toDateString(), 'recorded_by' => $this->staff->id]);
    $record()->assertSessionHasErrors('milestone');
    $payment->update(['status' => BiddingFeePayment::STATUS_VERIFIED, 'verified_at' => now()]);

    $record()->assertSessionHasNoErrors();
    expect($bid->fresh()->isDraft())->toBeFalse()->and($bid->fresh()->receipt_no)->toBe('LOG-2026-031');

    // An online project never takes sealed bids.
    $online = ($this->openProject)(['title' => 'Online Project', 'reference_no' => 'SJOM-2026-G-003']);
    $draft = Bid::create(['project_id' => $online->id, 'user_id' => $this->bidder->id, 'bid_amount' => 1, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_MANUAL]);
    expect(app(BidWorkflow::class)->availableActions($draft, $this->admin))->toBe([]);
});

it('closes the contract with inspection and acceptance after the Notice to Proceed', function () {
    $project = ($this->openProject)(['status' => 'awarded']);
    $bid = Bid::create([
        'project_id' => $project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 2400000,
        'status' => 'awarded', 'workflow_step' => Bid::STEP_CONTRACT_SIGNED,
        'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subDays(20),
        'notice_of_award_at' => now()->subDays(10), 'contract_signed_at' => now()->subDays(8),
    ]);
    $local = fn ($date) => $date->copy()->timezone(config('bac-office.display_timezone'))->format('Y-m-d\TH:i');
    $inspect = fn (array $data) => testCase()->actingAs($this->staff)->post(route('staff.procurement.inspection', $project), $data);
    $accept = fn (array $data) => testCase()->actingAs($this->admin)->post(route('admin.procurement.acceptance', $project), $data);

    $inspect(['occurred_at' => $local(now()->subDay()), 'reference_no' => 'IR-001'])->assertSessionHasErrors('occurred_at');

    $bid->update(['workflow_step' => Bid::STEP_NOTICE_TO_PROCEED, 'notice_to_proceed_at' => now()->subDays(7)]);

    $accept(['occurred_at' => $local(now()->subDay()), 'reference_no' => 'IAR-001'])->assertSessionHasErrors('occurred_at');
    $inspect(['occurred_at' => $local(now()->subDays(8)), 'reference_no' => 'IR-001'])->assertSessionHasErrors('occurred_at');
    $inspect(['occurred_at' => $local(now()->subDays(2))])->assertSessionHasErrors('reference_no');
    $inspect(['occurred_at' => $local(now()->subDays(2)), 'reference_no' => 'IR-001', 'document' => ($this->pdf)('inspection.pdf')])->assertSessionHasNoErrors();

    $accept(['occurred_at' => $local(now()->subDays(3)), 'reference_no' => 'IAR-001'])->assertSessionHasErrors('occurred_at');
    $accept(['occurred_at' => $local(now()->subDay()), 'reference_no' => 'IAR-2026-015'])->assertSessionHasNoErrors();

    $project->refresh();
    expect($project->isCompleted())->toBeTrue()
        ->and($project->status)->toBe('awarded')
        ->and($project->portalStatus()['label'])->toBe('Completed')
        ->and($bid->fresh()->workflow_step)->toBe(Bid::STEP_PROJECT_COMPLETED);

    $accept(['occurred_at' => $local(now()->subHour()), 'reference_no' => 'IAR-2'])->assertSessionHasErrors('occurred_at');

    $timeline = ProcurementTimeline::forProject($project->fresh());
    expect($timeline->current())->toBeNull()
        ->and($timeline->nextAction()['title'])->toBe('Procurement completed')
        ->and(collect($timeline->stages())->firstWhere('key', 'acceptance')['state'])->toBe('done');
});

it('shows skipped and stopped stages for direct projects and failed biddings', function () {
    $project = ($this->openProject)();
    $stages = collect(ProcurementTimeline::forProject($project)->stages())->keyBy('key');

    expect($stages['request']['state'])->toBe('skipped')
        ->and($stages['review']['state'])->toBe('skipped')
        ->and($stages['prebid']['state'])->toBe('skipped')
        ->and($stages['submission']['state'])->toBe('current');

    $project->update(['failed_bidding_at' => now(), 'failed_bidding_reason' => 'No bids received.']);
    $timeline = ProcurementTimeline::forProject($project->fresh());
    $stages = collect($timeline->stages())->keyBy('key');

    expect($stages['submission']['state'])->toBe('stopped')
        ->and($stages['acceptance']['state'])->toBe('stopped')
        ->and($timeline->nextAction()['title'])->toBe('Failure of bidding declared');

    testCase()->actingAs($this->admin)->get(route('admin.procurement.show', $project))->assertOk()
        ->assertSee('Failure of bidding declared')
        ->assertSee('No bids received.');
});

it('creates consecutive purchase requests alongside nonnumeric test references', function () {
    $prefix = 'PR-'.now()->format('Y').'-';
    foreach (['0001', 'T001'] as $suffix) {
        ProcurementRequest::create($this->requestData + [
            'reference_no' => $prefix.$suffix,
            'end_user_office' => $this->engineering->office,
            'requested_by' => $this->engineering->id,
            'status' => 'draft',
        ]);
    }

    foreach (['0002', '0003'] as $suffix) {
        $response = testCase()->actingAs($this->engineering)->post('/end-user/requests', $this->requestData + ['action' => 'draft']);
        $response->assertSessionHasNoErrors()->assertRedirect();
        testCase()->assertDatabaseHas('procurement_requests', ['reference_no' => $prefix.$suffix]);
    }
    expect(ProcurementRequest::count())->toBe(4);
});

it('increments the numeric purchase request sequence beyond four digits', function () {
    $prefix = 'PR-'.now()->format('Y').'-';
    foreach (['9999', '10000', 'T999'] as $suffix) {
        ProcurementRequest::create($this->requestData + [
            'reference_no' => $prefix.$suffix,
            'end_user_office' => $this->engineering->office,
            'status' => 'draft',
        ]);
    }
    expect(ProcurementRequest::nextReferenceNo())->toBe($prefix.'10001');
});

it('starts a new year purchase request sequence independently of earlier references', function () {
    ProcurementRequest::create($this->requestData + [
        'reference_no' => 'PR-'.now()->subYear()->format('Y').'-9999',
        'end_user_office' => $this->engineering->office,
        'status' => 'draft',
    ]);
    expect(ProcurementRequest::nextReferenceNo())->toBe('PR-'.now()->format('Y').'-0001');
});
