<?php

use App\Models\Award;
use App\Models\Bid;
use App\Models\ContractImplementation;
use App\Models\ContractImplementationEvent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    Storage::fake('local');
    $createUser = fn (string $role, string $email, ?string $office = null) => User::create([
        'name' => ucfirst(str_replace('_', ' ', $role)).' '.substr(md5($email), 0, 4),
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => $role,
        'status' => 'active',
        'office' => $office,
        'company' => $role === 'bidder' ? 'Infrastructure Winner Ltd.' : null,
    ]);
    $this->admin = $createUser('admin', 'infra-admin@example.test');
    $this->supplier = $createUser('bidder', 'infra-supplier@example.test');
    $this->otherBidder = $createUser('bidder', 'infra-other@example.test');
    $this->office = $createUser('end_user', 'infra-office@example.test', 'Municipal Engineering Office');
    $this->otherOffice = $createUser('end_user', 'infra-other-office@example.test', 'Municipal Health Office');
    $this->file = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF");

    $project = Project::create([
        'title' => 'Road rehabilitation, Barangay Sample',
        'description' => 'Infrastructure tracking feature test.',
        'reference_no' => 'SJOM-INFRA-TEST',
        'category' => 'infrastructure',
        'end_user_unit' => 'Municipal Engineering Office',
        'procurement_mode' => 'public_bidding',
        'legal_basis' => 'ra_12009',
        'budget' => 5000000,
        'contract_duration' => '90 calendar days from NTP',
        'location' => 'Barangay Sample, San Jose',
        'status' => 'awarded',
        'deadline' => now()->subDays(5),
    ]);
    $bid = Bid::create([
        'project_id' => $project->id,
        'user_id' => $this->supplier->id,
        'bid_amount' => 4900000,
        'status' => 'awarded',
        'workflow_step' => Bid::STEP_NOTICE_TO_PROCEED,
        'submission_channel' => Bid::CHANNEL_ELECTRONIC,
        'submitted_at' => now()->subDays(30),
        'contract_signed_at' => now()->subDays(4),
        'contract_signed_by' => $this->admin->id,
        'notice_of_award_at' => now()->subDays(6),
        'notice_to_proceed_at' => now()->subDays(2),
        'notice_to_proceed_by' => $this->admin->id,
    ]);
    $this->award = Award::create([
        'project_id' => $project->id,
        'bid_id' => $bid->id,
        'bidder_id' => $this->supplier->id,
        'contract_amount' => 4900000,
        'contract_date' => now()->subDays(4)->toDateString(),
        'notice_of_award_date' => now()->subDays(6)->toDateString(),
        'status' => 'active',
    ])->load(['project', 'bid.user']);
    $this->terms = [
        'delivery_deadline' => now()->addDays(30)->toDateString(),
        'delivery_location' => 'Road rehabilitation project site, Barangay Sample',
        'signed_contract_reference' => 'Infrastructure Contract No. 2026-01',
        'contract_items' => [['description' => 'Concrete pavement', 'quantity' => 250, 'unit' => 'linear meters']],
        'remarks' => 'Copied from the signed contract and NTP records.',
        'document' => ($this->file)('signed-contract.pdf'),
    ];
});

it('tracks infrastructure through progress, correction, inspection, acceptance, and completion', function () {
    testCase()->actingAs($this->admin)->get(route('admin.infrastructure.show', $this->award))->assertOk()->assertSee('Record terms from signed contract');
    testCase()->actingAs($this->admin)->put(route('admin.infrastructure.configure', $this->award), $this->terms)->assertRedirect();
    expect(ContractImplementation::where('award_id', $this->award->id)->value('status'))->toBe(ContractImplementation::INFRA_IN_PROGRESS);
    $termsEvent = ContractImplementationEvent::where('action', 'infrastructure_terms_recorded')->firstOrFail();
    expect($this->office->role)->toBe('end_user')->and($this->office->office)->toBe('Municipal Engineering Office')->and($termsEvent->implementation->award->project->end_user_unit)->toBe('Municipal Engineering Office');
    testCase()->actingAs($this->otherBidder)->get(route('infrastructure.document', $termsEvent))->assertForbidden();
    testCase()->actingAs($this->supplier)->get(route('infrastructure.document', $termsEvent))->assertOk();
    testCase()->actingAs($this->office)->get(route('infrastructure.document', $termsEvent))->assertOk();

    testCase()->actingAs($this->supplier)->get(route('bidder.infrastructure.show', $this->award))->assertOk()->assertSee('Submit progress update');
    testCase()->actingAs($this->supplier)->post(route('bidder.infrastructure.progress', $this->award), [
        'progress_percent' => 100, 'milestone' => 'Road works completed', 'remarks' => 'Requesting inspection.',
        'request_inspection' => 1, 'document' => ($this->file)('progress.pdf'),
    ])->assertRedirect();
    expect(ContractImplementation::where('award_id', $this->award->id)->value('status'))->toBe(ContractImplementation::INFRA_FOR_INSPECTION);

    testCase()->actingAs($this->office)->post(route('end-user.infrastructure.inspect', $this->award), [
        'outcome' => 'correction', 'findings' => 'Inspection found two sections needing repair.', 'deficiencies' => 'Repair two pavement sections.',
        'remarks' => 'Please correct and request reinspection.', 'document' => ($this->file)('inspection-correction.pdf'),
    ])->assertRedirect();
    expect(ContractImplementation::where('award_id', $this->award->id)->value('status'))->toBe(ContractImplementation::INFRA_FOR_CORRECTION);

    testCase()->actingAs($this->supplier)->post(route('bidder.infrastructure.progress', $this->award), [
        'progress_percent' => 100, 'milestone' => 'Repairs completed', 'remarks' => 'Corrections completed; requesting reinspection.',
        'request_inspection' => 1, 'document' => ($this->file)('correction-proof.pdf'),
    ])->assertRedirect();
    testCase()->actingAs($this->office)->post(route('end-user.infrastructure.inspect', $this->award), [
        'outcome' => 'recommend_acceptance', 'findings' => 'Repairs are complete and conform to the contract.', 'remarks' => 'Recommend acceptance.',
        'document' => ($this->file)('inspection-pass.pdf'),
    ])->assertRedirect();

    foreach (['accept', 'payment_processing', 'paid', 'complete'] as $action) {
        testCase()->actingAs($this->admin)->post(route('admin.infrastructure.action', $this->award), [
            'action' => $action, 'remarks' => ucfirst(str_replace('_', ' ', $action)).' recorded.', 'document' => ($this->file)($action.'.pdf'),
        ])->assertRedirect();
    }
    $record = ContractImplementation::where('award_id', $this->award->id)->firstOrFail();
    expect($record->status)->toBe(ContractImplementation::INFRA_COMPLETED)
        ->and(ContractImplementationEvent::where('contract_implementation_id', $record->id)->count())->toBe(9)
        ->and($record->events()->where('action', 'infrastructure_paid')->first()?->actor_id)->toBe($this->admin->id);
    testCase()->actingAs($this->supplier)->get(route('bidder.infrastructure.show', $this->award))->assertOk()->assertSee('Completed');
});

it('restricts Infrastructure tracking to the winner, matching end-user office, and assigned LGU staff', function () {
    testCase()->actingAs($this->otherBidder)->get(route('bidder.infrastructure.show', $this->award))->assertForbidden();
    testCase()->actingAs($this->otherOffice)->get(route('end-user.infrastructure.index'))->assertOk()->assertDontSee($this->award->project->title);
    testCase()->actingAs($this->otherOffice)->post(route('end-user.infrastructure.inspect', $this->award), [])->assertForbidden();
});

it('requires an existing signed contract and NTP, and rejects out-of-order actions', function () {
    testCase()->actingAs($this->admin)->post(route('admin.infrastructure.action', $this->award), [
        'action' => 'complete', 'remarks' => 'Skip stages', 'document' => ($this->file)('premature.pdf'),
    ])->assertSessionHasErrors('status');
    expect(ContractImplementation::where('award_id', $this->award->id)->value('status'))->toBe(ContractImplementation::INFRA_IN_PROGRESS)->and(ContractImplementationEvent::count())->toBe(0);

    $this->award->bid->update(['notice_to_proceed_at' => null]);
    testCase()->actingAs($this->admin)->get(route('admin.infrastructure.show', $this->award))->assertNotFound();
});

it('rejects an accidental duplicate Infrastructure progress post without blocking later reports', function () {
    testCase()->actingAs($this->admin)->put(route('admin.infrastructure.configure', $this->award), $this->terms)->assertRedirect();
    $report = ['progress_percent' => 25, 'milestone' => 'Foundation work', 'remarks' => 'Daily progress report.', 'document' => ($this->file)('foundation.pdf')];
    testCase()->actingAs($this->supplier)->post(route('bidder.infrastructure.progress', $this->award), $report)->assertRedirect();
    testCase()->actingAs($this->supplier)->post(route('bidder.infrastructure.progress', $this->award), array_merge($report, ['document' => ($this->file)('foundation-duplicate.pdf')]))->assertSessionHasErrors('submission');
    expect(ContractImplementationEvent::where('action', 'infrastructure_progress_submitted')->count())->toBe(1);
});
