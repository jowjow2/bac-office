<?php

use App\Models\AuditLog;
use App\Models\Assignment;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();

    $this->admin = User::create([
        'name' => 'BAC Security Admin',
        'email' => 'security-admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $this->staff = User::create([
        'name' => 'BAC Secretariat Staff',
        'email' => 'security-staff@example.com',
        'password' => Hash::make('password'),
        'role' => 'staff',
        'status' => 'active',
    ]);

    $this->bidder = User::create([
        'name' => 'PCIC Contact',
        'email' => 'pcic-security@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'PCIC Corporation',
    ]);

    $this->project = Project::create([
        'reference_no' => 'SJ-BAC-SEC-001',
        'title' => 'San Jose Farm-to-Market Road',
        'description' => 'Procurement security test project.',
        'budget' => 950000,
        'status' => 'open',
        'deadline' => now()->subHour(),
        'procurement_mode' => 'public_bidding',
        'legal_basis' => 'ra_12009',
        'submission_mode' => Project::SUBMISSION_ELECTRONIC,
    ]);

    ProjectSchedule::create([
        'project_id' => $this->project->id,
        'bid_submission_deadline' => now()->subHour(),
        'bid_opening_date' => now()->subMinute(),
    ]);

    ProjectRequirement::create([
        'project_id' => $this->project->id,
        'required_documents' => ['Technical Proposal', 'Financial Proposal'],
    ]);

    $this->bid = Bid::create([
        'project_id' => $this->project->id,
        'user_id' => $this->bidder->id,
        'bid_amount' => 812345.67,
        'proposal_file' => 'proposals/pcic-secret.pdf',
        'eligibility_file' => 'eligibility/pcic-secret.pdf',
        'status' => 'pending',
        'workflow_step' => Bid::STEP_SUBMITTED,
        'submission_channel' => Bid::CHANNEL_MANUAL,
        'submitted_at' => now()->subMinutes(10),
        'receipt_no' => 'SJ-SEC-0001',
    ]);

    Assignment::create([
        'staff_id' => $this->staff->id,
        'project_id' => $this->project->id,
        'role_in_project' => 'Evaluator',
    ]);

    Storage::disk('local')->put('proposals/pcic-secret.pdf', '%PDF-1.4 sealed proposal');
    Storage::disk('local')->put('eligibility/pcic-secret.pdf', '%PDF-1.4 sealed eligibility');

});

it('keeps sealed amounts, filenames, and files out of BAC screens and JSON', function () {
    $response = testCase()->actingAs($this->admin)->get(route('admin.bids'));

    $response->assertOk()
        ->assertSee('View sealed submission')
        ->assertSee('Sealed')
        ->assertSee('Sealed envelope')
        ->assertDontSee('812,345.67')
        ->assertDontSee('pcic-secret.pdf')
        ->assertDontSee('Proposal: Missing');
    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.view', $this->bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Submission sealed')
        ->assertDontSee('812,345.67')
        ->assertDontSee('pcic-secret.pdf')
        // The only action on a sealed bid is recording its actual opening.
        ->assertSee(route('admin.project.open-bids', $this->project), false)
        ->assertDontSee('Internal Notes')
        ->assertDontSee(route('admin.bid.decision', $this->bid), false)
        ->assertDontSee(route('admin.bid.document.pdf', ['bid' => $this->bid, 'document' => 'proposal']), false);

    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.document.pdf', ['bid' => $this->bid, 'document' => 'proposal']))
        ->assertForbidden();

    $component = BidDocument::create([
        'bid_id' => $this->bid->id,
        'requirement_key' => 'sealed_component',
        'component' => BidDocument::COMPONENT_TECHNICAL,
        'label' => 'Sealed component',
        'file_path' => 'proposals/pcic-secret.pdf',
        'original_name' => 'pcic-secret.pdf',
        'size' => 25,
    ]);

    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.component-file', ['bid' => $this->bid, 'bidDocument' => $component]))
        ->assertForbidden();

    testCase()->actingAs($this->staff)
        ->getJson(route('staff.review-bids.show', $this->bid))
        ->assertOk()
        ->assertJsonPath('bid.bid_amount', null)
        ->assertJsonPath('bid.proposal_url', null)
        ->assertJsonPath('bid.document_checklist.0.file_name', null)
        ->assertJsonPath('bid.document_status.key', 'sealed_envelope');
});

it('requires explicit opening, records Manila server time and writes one audit event', function () {
    testCase()->actingAs($this->admin)
        ->post(route('admin.project.open-bids', $this->project))
        ->assertSessionHasNoErrors();

    $opened = $this->project->fresh();

    expect($opened->bids_opened_by)->toBe($this->admin->id)
        ->and($opened->bids_opened_at)->not->toBeNull()
        ->and($opened->bids_opened_at->timezone('Asia/Manila')->getTimezone()->getName())->toBe('Asia/Manila');

    $audit = AuditLog::query()
        ->where('action', 'bids_opened')
        ->where('auditable_id', $this->project->id)
        ->sole();

    expect($audit->user_id)->toBe($this->admin->id)
        ->and($audit->new_values['bids_opened_by'])->toBe($this->admin->id)
        ->and($audit->new_values['timezone'])->toBe('Asia/Manila');

    testCase()->actingAs($this->admin)
        ->post(route('admin.project.open-bids', $this->project))
        ->assertSessionHasErrors('bids_opened_at');

    expect(AuditLog::where('action', 'bids_opened')->where('auditable_id', $this->project->id)->count())->toBe(1);
});

it('does not apply competitive opening to an RFQ quotation', function () {
    $this->project->update([
        'procurement_mode' => 'small_value_procurement',
        'legal_basis' => 'ra_12009',
    ]);

    expect($this->bid->fresh()->isSealed())->toBeFalse()
        ->and($this->bid->fresh()->isFinancialSealed())->toBeFalse();

    testCase()->actingAs($this->admin)->get(route('admin.bids'))
        ->assertSee('Small Value Procurement')
        ->assertSee('Review quotation')
        ->assertSee('812,345.67')
        ->assertDontSee('View sealed submission');

    testCase()->actingAs($this->admin)
        ->post(route('admin.project.open-bids', $this->project))
        ->assertSessionHasErrors('bids_opened_at');

    expect($this->project->fresh()->bids_opened_at)->toBeNull();
});

it('records a structured preliminary result after opening instead of treating review as award', function () {
    $this->bid->update(['submission_channel' => Bid::CHANNEL_ELECTRONIC]);
    BidDocument::create([
        'bid_id' => $this->bid->id,
        'requirement_key' => 'technical_proposal',
        'component' => BidDocument::COMPONENT_TECHNICAL,
        'label' => 'Technical Proposal',
        'file_path' => 'proposals/pcic-secret.pdf',
        'original_name' => 'technical.pdf',
        'size' => 25,
    ]);
    BidDocument::create([
        'bid_id' => $this->bid->id,
        'requirement_key' => 'financial_bid_form',
        'component' => BidDocument::COMPONENT_FINANCIAL,
        'label' => 'Financial Bid Form',
        'file_path' => 'eligibility/pcic-secret.pdf',
        'original_name' => 'financial.pdf',
        'size' => 28,
    ]);
    testCase()->actingAs($this->admin)
        ->post(route('admin.project.open-bids', $this->project))
        ->assertSessionHasNoErrors();

    testCase()->actingAs($this->admin)
        ->post(route('admin.bid.decision', $this->bid), [
            'action' => BidWorkflow::FAIL_PRELIMINARY,
            'reason' => 'Required documents failed preliminary examination.',
            'failed_requirements' => ['technical_proposal', 'financial_bid_form'],
        ])
        ->assertSessionHasNoErrors();

    $event = $this->bid->trackings()->where('stage', 'preliminary_examination')->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->created_by)->toBe($this->admin->id)
        ->and($event->decision)->toBe('failed')
        ->and(collect($event->details['requirements'])->pluck('result')->contains('failed'))->toBeTrue()
        ->and($this->bid->fresh()->award_decision)->toBeNull();

    testCase()->actingAs($this->admin)->get(route('admin.bid.view', $this->bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Preliminary examination')
        ->assertSee('BAC Security Admin')
        ->assertSee('Review Bid');
});


it('enforces the complete Admin review sequence and keeps recommendation separate from award', function () {
    $this->bid->update(['submission_channel' => Bid::CHANNEL_ELECTRONIC]);
    foreach ([
        ['key' => 'technical_proposal', 'component' => BidDocument::COMPONENT_TECHNICAL, 'label' => 'Technical Proposal', 'path' => 'proposals/pcic-secret.pdf'],
        ['key' => 'financial_bid_form', 'component' => BidDocument::COMPONENT_FINANCIAL, 'label' => 'Financial Bid Form', 'path' => 'eligibility/pcic-secret.pdf'],
    ] as $document) {
        BidDocument::create(['bid_id' => $this->bid->id, 'requirement_key' => $document['key'], 'component' => $document['component'], 'label' => $document['label'], 'file_path' => $document['path'], 'original_name' => $document['label'] . '.pdf', 'size' => 25]);
    }

    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $this->project))->assertSessionHasNoErrors();

    $completeChecklist = $this->bid->fresh()->documentChecklist();
    foreach (collect($completeChecklist)->where('submitted', false) as $item) {
        BidDocument::create(['bid_id' => $this->bid->id, 'requirement_key' => $item['key'], 'component' => $item['component'], 'label' => $item['label'], 'file_path' => 'proposals/pcic-secret.pdf', 'original_name' => $item['label'] . '.pdf', 'size' => 25]);
    }
    $allChecklistKeys = collect($this->bid->fresh()->documentChecklist())->pluck('key')->all();

    testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $this->bid), ['action' => BidWorkflow::EVALUATE, 'evaluation_result' => 'responsive', 'evaluation_findings' => 'Too early.'])->assertSessionHasErrors('milestone');
    testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $this->bid), ['action' => BidWorkflow::PASS_PRELIMINARY, 'verified_requirements' => $allChecklistKeys])->assertSessionHasNoErrors();

    testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $this->bid), ['action' => BidWorkflow::EVALUATE, 'evaluation_result' => 'responsive', 'evaluation_findings' => 'Too early.'])->assertSessionHasErrors('milestone');
    testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $this->bid), ['action' => BidWorkflow::START_EVALUATION])->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $this->bid), ['action' => BidWorkflow::EVALUATE, 'evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the configured technical and award criteria.', 'criterion_results' => ['technical_requirements' => 'meets']])->assertSessionHasNoErrors();

    $evaluation = $this->bid->trackings()->where('stage', 'bid_evaluation')->where('decision', 'passed')->latest('id')->firstOrFail();
    expect($evaluation->created_by)->toBe($this->admin->id)
        ->and($evaluation->details['result'])->toBe('responsive')
        ->and($evaluation->details['findings'])->toContain('Responsive');

    testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $this->bid), ['action' => BidWorkflow::START_POST_QUALIFICATION])->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $this->bid), ['action' => BidWorkflow::PASS_POST_QUALIFICATION, 'post_qualification_findings' => 'Qualification documents, capacity and eligibility verified.'])->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $this->bid), ['action' => BidWorkflow::RECOMMEND, 'bac_resolution_no' => 'BAC Resolution No. 2026-SEC-01', 'bac_resolution_date' => now()->toDateString()])->assertSessionHasNoErrors();

    $final = $this->bid->fresh();
    expect($final->post_qualification_result)->toBe(Bid::POST_QUALIFICATION_PASSED)
        ->and($final->bac_recommended_by)->toBe($this->admin->id)
        ->and($final->award_decision)->toBeNull()
        ->and(\App\Models\Award::where('bid_id', $final->id)->exists())->toBeFalse();

    testCase()->actingAs($this->admin)->get(route('admin.bid.view', $final), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Responsive against the configured technical and award criteria.')
        ->assertSee('Qualification documents, capacity and eligibility verified.')
        ->assertSee('BAC Security Admin');
});


it('uses the project-configured financial reveal stage instead of assuming one universal opening point', function () {
    $this->project->update(['financial_opening_stage' => 'at_opening']);
    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $this->project))->assertSessionHasNoErrors();
    expect($this->bid->fresh()->isFinancialSealed())->toBeFalse();
    testCase()->actingAs($this->admin)->get(route('admin.bids'))->assertSee('812,345.67');

    $this->project->update(['financial_opening_stage' => 'after_evaluation_start']);
    expect($this->bid->fresh()->isFinancialSealed())->toBeTrue();
    testCase()->actingAs($this->admin)->get(route('admin.bids'))->assertDontSee('812,345.67');
});
