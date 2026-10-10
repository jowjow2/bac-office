<?php

use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The create-procurement-project wizard: linked purchase request, every
 * existing field, draft saving and publication in this BAC system.
 */
beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'wizard-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->request = ProcurementRequest::create([
        'reference_no' => 'PR-2026-0042', 'end_user_office' => 'Municipal Agriculture Office', 'requested_by' => $this->admin->id,
        'title' => 'Supply of vegetable seeds', 'category' => 'goods', 'specifications' => 'Hybrid seeds, assorted, 200 packs',
        'quantity' => 200, 'unit' => 'packs', 'estimated_cost' => 180000, 'fund_source' => 'General Fund',
        'delivery_period' => '15 calendar days', 'status' => ProcurementRequest::STATUS_FORWARDED,
        'ppmp_reference' => 'PPMP-MAO-2026-7', 'app_reference' => 'APP-2026-31', 'submitted_at' => now()->subDays(3), 'forwarded_at' => now()->subDay(),
    ]);
    $this->pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    $this->local = fn ($date) => $date->format('Y-m-d\TH:i');
});

it('shows the linked purchase request and every field of the five steps', function () {
    $response = testCase()->actingAs($this->admin)->get(route('admin.projects.create', ['request' => $this->request->id]))->assertOk();

    $response->assertSee('Linked purchase request')
        ->assertSee('PR-2026-0042')
        ->assertSee('Municipal Agriculture Office')
        ->assertSee('Ready for procurement')
        ->assertSee('From PR')
        ->assertSee('value="'.$this->request->id.'"', false)
        ->assertSee('value="Supply of vegetable seeds"', false)
        ->assertSee('value="180000.00"', false);

    foreach ([
        'title', 'description', 'category', 'location', 'procurement_mode', 'negotiation_ground', 'legal_basis', 'end_user_unit',
        'source_of_fund', 'contract_duration', 'budget', 'philgeps_reference_no', 'philgeps_url',
        'required_documents[]', 'eligibility_requirements', 'technical_requirements', 'financial_requirements', 'qualification_notes', 'special_instructions',
        'submission_mode', 'submission_venue', 'bidding_documents_fee', 'payment_venue', 'bid_security_required', 'bid_security_notes', 'electronic_submission_authority',
        'document_type[]', 'project_documents[]',
        'date_posted', 'pre_bid_conference_date', 'bid_submission_deadline', 'bid_opening_date',
        'confirm_correct', 'status',
    ] as $name) {
        $response->assertSee('name="'.$name.'"', false);
    }

    // Registration documents covered by the PhilGEPS Platinum certificate are not asked per bid.
    foreach (['Business Permit', 'DTI/SEC Registration', 'Tax Clearance', 'Company Profile'] as $registrationDocument) {
        $response->assertDontSee('value="'.$registrationDocument.'"', false);
    }

    // The external PhilGEPS record is never required here.
    expect(preg_match('/<input[^>]*name="philgeps_reference_no"[^>]*\brequired\b/', $response->getContent()))->toBe(0)
        ->and(preg_match('/<input[^>]*name="philgeps_url"[^>]*\brequired\b/', $response->getContent()))->toBe(0);
});

it('saves a draft linked to the request with the requirement details and a custom required document', function () {
    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), [
        'status' => 'draft',
        'procurement_request_id' => $this->request->id,
        'title' => 'Supply of vegetable seeds',
        'procurement_mode' => 'small_value_procurement',
        'required_documents' => ['PhilGEPS Registration', 'Certificate of seed quality (BPI)'],
        'eligibility_requirements' => 'Registered seed dealer',
        'special_instructions' => 'Deliver to the Municipal Agriculture Office',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $project = Project::with('requirement')->firstOrFail();
    expect($project->status)->toBe('draft')
        ->and($project->procurement_request_id)->toBe($this->request->id)
        ->and($project->requirement->required_documents)->toBe(['PhilGEPS Registration', 'Certificate of seed quality (BPI)'])
        ->and($project->requirement->eligibility_requirements)->toBe('Registered seed dealer')
        ->and($this->request->fresh()->status)->toBe(ProcurementRequest::STATUS_IN_PROCUREMENT);
});

it('publishes competitive bidding in the BAC system with each file stored under its own type', function () {
    $deadline = workdayAt(20, 10, 0);

    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), [
        'status' => 'open',
        'confirm_correct' => '1',
        'procurement_request_id' => $this->request->id,
        'title' => 'Supply of vegetable seeds',
        'description' => 'Hybrid seeds, assorted, 200 packs',
        'category' => 'goods',
        'location' => 'San Jose, Occidental Mindoro',
        'procurement_mode' => 'public_bidding',
        'legal_basis' => 'ra_12009',
        'award_criterion' => 'lowest_calculated_bid',
        'bid_opening_venue' => 'BAC Conference Room, Municipal Hall',
        'electronic_submission_authority' => 'MIS Certification No. 2026-03',
        'source_of_fund' => 'General Fund',
        'contract_duration' => '15 calendar days',
        'budget' => '180000.00',
        'bid_submission_deadline' => ($this->local)($deadline),
        'bid_opening_date' => ($this->local)($deadline->copy()->setTime(10, 30)),
        'document_type' => ['invitation_to_bid', 'terms_of_reference'],
        'project_documents' => [($this->pdf)('itb.pdf'), ($this->pdf)('tor.pdf')],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $project = Project::with('documents')->firstOrFail();
    expect($project->status)->toBe('open')
        ->and($project->published_at)->not->toBeNull()
        ->and($project->philgeps_reference_no)->toBeNull()
        ->and($project->documents->pluck('document_type', 'original_name')->all())->toBe(['itb.pdf' => 'invitation_to_bid', 'tor.pdf' => 'terms_of_reference']);
});

it('keeps the project as a draft with the reasons when a publication check fails', function () {
    $deadline = workdayAt(20, 10, 0);

    testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), [
        'status' => 'open',
        'confirm_correct' => '1',
        'title' => 'Road maintenance',
        'description' => 'Routine maintenance',
        'category' => 'infrastructure',
        'location' => 'San Jose',
        'procurement_mode' => 'public_bidding',
        'legal_basis' => 'ra_12009',
        'source_of_fund' => 'General Fund',
        'contract_duration' => '30 Calendar Days',
        'budget' => '900000.00',
        'bid_submission_deadline' => ($this->local)($deadline),
        'bid_opening_date' => ($this->local)($deadline->copy()->setTime(10, 30)),
    ])->assertRedirect()->assertSessionHas('error', fn (string $message) => str_contains($message, 'Invitation to Bid'));

    expect(Project::firstOrFail()->status)->toBe('draft');
});

it('refuses to publish without the confirmation and returns to the wizard', function () {
    testCase()->actingAs($this->admin)
        ->from(route('admin.projects.create'))
        ->post(route('admin.projects.wizard.store'), ['status' => 'open', 'title' => 'Incomplete'])
        ->assertRedirect(route('admin.projects.create'))
        ->assertSessionHasErrors(['confirm_correct', 'description', 'budget', 'bid_submission_deadline']);

    expect(Project::count())->toBe(0);

    testCase()->actingAs($this->admin)->get(route('admin.projects.create'))->assertOk();
});

it('gives the wizard the same standard documents bidders will be asked for', function () {
    $sets = \App\Support\BidSubmissionRequirements::wizardPreview();
    $labels = fn (string $basis, string $category, string $mode, string $band) => array_column($sets[$basis][$category][$mode][$band], 'label');

    expect($labels('ra_12009', 'infrastructure', 'public_bidding', 'high'))->toContain('Priced Bill of Quantities', 'Omnibus Sworn Statement')
        // Small purchases ask for less; the Omnibus Sworn Statement only above ₱50,000.
        ->and($labels('ra_12009', 'goods', 'small_value_procurement', 'low'))->toContain('Price Quotation / Proposal Form')->not->toContain('Omnibus Sworn Statement')
        ->and($labels('ra_12009', 'goods', 'small_value_procurement', 'mid'))->toContain('Omnibus Sworn Statement')
        ->and($labels('ra_12009', 'goods', 'direct_contracting', 'low'))->toContain('Certificate of exclusive manufacturer / distributor');
});
