<?php

use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\BidTracking;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidSubmissionRequirements;
use App\Support\BidWorkflow;
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

    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'sub-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->bidder = User::create(['name' => 'Juan Builder', 'email' => 'sub-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Builders']);

    $this->makeProject = function (array $attributes = []): Project {
        $project = Project::create(array_merge([
            'title' => 'Concreting of Barangay Road',
            'description' => 'Road concreting works.',
            'reference_no' => 'SJOM-2026-INFRA-014',
            'philgeps_reference_no' => '12345678',
            'category' => 'infrastructure',
            'procurement_mode' => 'public_bidding',
            'budget' => 2500000,
            'deadline' => now()->addDays(5),
            'status' => 'open',
            'submission_venue' => 'BAC Secretariat, Municipal Hall',
        ], $attributes));
        ProjectSchedule::create(['project_id' => $project->id, 'bid_submission_deadline' => $project->deadline, 'bid_opening_date' => $project->deadline->copy()->addHour()]);

        return $project->fresh();
    };

    $this->authorizeElectronic = fn (Project $project) => $project->update([
        'submission_mode' => Project::SUBMISSION_ELECTRONIC,
        'electronic_submission_authority' => 'BAC Resolution No. 2026-014',
        'electronic_submission_authorized_at' => now(),
        'electronic_submission_authorized_by' => $this->admin->id,
    ]);

    $this->pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

    $this->allRequiredFiles = function (Project $project): array {
        return collect(BidSubmissionRequirements::for($project)->requiredKeys())
            ->mapWithKeys(fn (string $key) => [$key => ($this->pdf)($key.'.pdf')])
            ->all();
    };

    $this->submit = fn (Project $project, array $data) => testCase()->actingAs($this->bidder)
        ->post(route('bidder.bids.store', $project), array_merge([
            'project_id' => $project->id,
            'financial_password' => '482913',
            'financial_password_confirmation' => '482913',
        ], $data));

    $this->track = fn () => testCase()->actingAs($this->bidder)->getJson(route('bidder.bidding-track.data'))->assertOk()->json('bids.0');
});

it('shows the project notice and a category-specific checklist in the submit modal', function () {
    $project = ($this->makeProject)();

    $response = testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))->assertOk();

    $response->assertSee('12345678')
        ->assertSee('SJOM-2026-INFRA-014')
        ->assertSee('Infrastructure Projects')
        // No legal basis recorded: RA 9184 rules and names apply.
        ->assertSee('Public Bidding')
        ->assertSee('2,500,000.00')
        ->assertSee('Bid Opening')
        ->assertSee('Technical Component (including Eligibility Documents)')
        ->assertSee('Financial Component')
        ->assertSee('Final submission check')
        ->assertSee('data-sb-panel="1"', false)
        ->assertSee('data-sb-panel="2"', false)
        ->assertSee('data-sb-panel="3"', false)
        ->assertSee('data-bid-submit', false)
        ->assertSee('data-bulk-uploader="technical"', false)
        ->assertSee('data-bulk-uploader="financial"', false)
        ->assertSee('multiple accept=', false)
        ->assertSee('Choose technical files')
        ->assertSee('Choose financial files')
        ->assertDontSee('>Choose files</span>', false)
        ->assertSee('class="sb-steps"', false)
        ->assertSee('data-sb-go="1"', false)
        ->assertSee('data-sb-go="2"', false)
        ->assertSee('data-sb-go="3"', false)
        ->assertSee('data-sb-next', false)
        ->assertSee('data-sb-prev', false)
        ->assertSee('data-sb-panel="2" aria-labelledby="bsm-financial-', false)
        ->assertSee('data-sb-panel="3" aria-labelledby="bsm-review-', false)
        ->assertSee('PCAB License and Registration')
        ->assertSee('Priced Bill of Quantities')
        ->assertSee('name="documents[bill_of_quantities]"', false)
        ->assertDontSee('2 files required')
        ->assertDontSee('name="proposal_file"', false);

    // Goods use different forms; no PCAB or Bill of Quantities.
    $goods = BidSubmissionRequirements::for(($this->makeProject)(['title' => 'Office Supplies', 'category' => 'goods']));
    expect($goods->items()->pluck('key')->all())->toContain('price_schedule', 'delivery_schedule')
        ->not->toContain('pcab', 'bill_of_quantities')
        ->and($goods->financial()->pluck('key')->all())->toBe(['financial_bid_form', 'price_schedule']);

    // RA 12009: the contractor's PCAB license is in its PhilGEPS Platinum registration; per bid only for a JV.
    $ra12009Infra = BidSubmissionRequirements::for(($this->makeProject)(['title' => 'Drainage', 'legal_basis' => 'ra_12009']));
    expect($ra12009Infra->find('pcab')['required'])->toBeFalse()
        ->and($ra12009Infra->requiredKeys())->toContain('philgeps', 'omnibus')->not->toContain('pcab');

    // Small Value Procurement: ABC-based conditions, not the public bidding set.
    $svpSmall = BidSubmissionRequirements::for(($this->makeProject)(['title' => 'Snacks', 'procurement_mode' => 'small_value_procurement', 'category' => 'goods', 'budget' => 40000]));
    $svpLarge = BidSubmissionRequirements::for(($this->makeProject)(['title' => 'Laptops', 'procurement_mode' => 'small_value_procurement', 'category' => 'goods', 'budget' => 600000]));
    expect($svpSmall->items()->pluck('key')->all())->toBe(['philgeps', 'mayors_permit', 'financial_bid_form'])
        ->and($svpLarge->items()->pluck('key')->all())->toContain('tax_return', 'omnibus')
        ->not->toContain('slcc', 'bid_security');
});

it('accepts online bids on every project without an LGU authority and never saves drafts', function () {
    $project = ($this->makeProject)();
    expect($project->submission_mode)->toBe(Project::SUBMISSION_ELECTRONIC)
        ->and($project->electronic_submission_authority)->toBeNull();

    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertSee('Official online submission')
        ->assertSee('Submit Official Bid')
        ->assertDontSee('Save Draft Record')
        ->assertDontSee('sealed bid envelopes');

    // The financial PIN is exactly 6 digits, typed twice.
    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertSee('Financial PIN')
        ->assertSee('inputmode="numeric" pattern="[0-9]{6}"', false);
    foreach ([['12345', '12345'], ['abcdef', 'abcdef'], ['1234567', '1234567'], ['482913', '482914']] as [$pin, $confirm]) {
        ($this->submit)($project, ['bid_amount' => '2400000', 'documents' => ($this->allRequiredFiles)($project), 'financial_password' => $pin, 'financial_password_confirmation' => $confirm])
            ->assertSessionHasErrors('financial_password');
    }
    expect(Bid::count())->toBe(0);

    // An incomplete upload is refused, not kept as a draft.
    ($this->submit)($project, [
        'bid_amount' => '2,400,000.00',
        'documents' => ['philgeps' => ($this->pdf)('philgeps.pdf')],
    ])->assertSessionHasErrors('documents');
    expect(Bid::count())->toBe(0);

    ($this->submit)($project, ['bid_amount' => '2400000', 'documents' => ($this->allRequiredFiles)($project)])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'Bid submitted online'));

    $bid = Bid::firstOrFail();
    expect($bid->isDraft())->toBeFalse()
        ->and($bid->submission_channel)->toBe(Bid::CHANNEL_ELECTRONIC)
        ->and($bid->receipt_no)->toStartWith('BAC-');
});

it('lets a bidder finish a draft saved before submission became online-only', function () {
    $project = ($this->makeProject)();
    $draft = Bid::create([
        'project_id' => $project->id,
        'user_id' => $this->bidder->id,
        'bid_amount' => 2400000,
        'status' => 'pending',
        'workflow_step' => Bid::STEP_SUBMITTED,
        'submission_channel' => Bid::CHANNEL_MANUAL,
    ]);

    expect($draft->isDraft())->toBeTrue()
        ->and($draft->progress()->adminStatus()['label'])->toBe('Draft (Not Official)')
        // No BAC decision can be recorded on a draft; there is no manual receipt any more.
        ->and(app(BidWorkflow::class)->availableActions($draft->fresh(), $this->admin))->toBe([]);

    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertSee('Continue draft');

    ($this->submit)($project, ['bid_amount' => '2300000', 'documents' => ($this->allRequiredFiles)($project)])
        ->assertSessionHasNoErrors();

    $draft->refresh();
    expect(Bid::count())->toBe(1)
        ->and($draft->isDraft())->toBeFalse()
        ->and($draft->submission_channel)->toBe(Bid::CHANNEL_ELECTRONIC)
        ->and($draft->bid_amount)->toBe('2300000.00')
        ->and($draft->progress()->adminStatus()['label'])->toBe('Submitted');
});

it('closes a draft that was never submitted online as not submitted', function () {
    $project = ($this->makeProject)();
    $bid = Bid::create([
        'project_id' => $project->id,
        'user_id' => $this->bidder->id,
        'bid_amount' => 2400000,
        'status' => 'pending',
        'workflow_step' => Bid::STEP_SUBMITTED,
        'submission_channel' => Bid::CHANNEL_MANUAL,
    ]);

    $this->travel(6)->days();

    expect($bid->fresh()->progress()->adminStatus()['label'])->toBe('Not Submitted');
    $track = ($this->track)();
    expect($track['outcome']['title'])->toBe('No Official Bid Received');

    // Opening the bids does not open drafts.
    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project))->assertSessionHasNoErrors();
    expect(BidTracking::where('bid_id', $bid->id)->where('decision', 'opened')->exists())->toBeFalse();
});

it('rejects an electronic submission with missing required documents', function () {
    $project = ($this->makeProject)();
    ($this->authorizeElectronic)($project);

    $files = ($this->allRequiredFiles)($project);
    unset($files['pcab'], $files['bill_of_quantities']);

    ($this->submit)($project, ['bid_amount' => '2400000', 'documents' => $files])
        ->assertSessionHasErrors(['documents', 'documents.pcab', 'documents.bill_of_quantities']);

    expect(Bid::count())->toBe(0)
        ->and(BidDocument::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('bid-submissions'))->toBe([]);
});

it('rejects a bid price above the ABC and keeps the entered amount exactly', function () {
    $project = ($this->makeProject)();
    ($this->authorizeElectronic)($project);

    ($this->submit)($project, ['bid_amount' => '2,500,000.01', 'documents' => ($this->allRequiredFiles)($project)])
        ->assertSessionHasErrors(['bid_amount' => 'Your bid price of ₱2,500,000.01 exceeds the Approved Budget for the Contract of ₱2,500,000.00. Bids above the ABC are not accepted.']);
    expect(Bid::count())->toBe(0);

    ($this->submit)($project, ['bid_amount' => '2,487,315.5', 'documents' => ($this->allRequiredFiles)($project)])
        ->assertSessionHasNoErrors();

    // Stored as entered ("2487315.50"), never recomputed. (SQLite keeps decimals
    // as REAL, so read it through the model's decimal:2 cast.)
    expect(Bid::firstOrFail()->bid_amount)->toBe('2487315.50');
});

it('refuses late submissions on the server', function () {
    $project = ($this->makeProject)();
    ($this->authorizeElectronic)($project);
    $files = ($this->allRequiredFiles)($project);

    $this->travel(5)->days();
    $this->travel(1)->minutes();

    ($this->submit)($project, ['bid_amount' => '2400000', 'documents' => $files])
        ->assertSessionHasErrors('deadline');

    expect(Bid::count())->toBe(0);
});

it('records an authorized electronic submission with receipt, files and a Bid Submitted event', function () {
    $project = ($this->makeProject)();
    ($this->authorizeElectronic)($project);

    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertSee('Official online submission')
        ->assertSee('BAC Resolution No. 2026-014')
        ->assertSee('Submit Official Bid');

    ($this->submit)($project, ['bid_amount' => '2400000', 'documents' => ($this->allRequiredFiles)($project)])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'Receipt No. BAC-'));

    $bid = Bid::with('documents')->firstOrFail();
    expect($bid->submission_channel)->toBe(Bid::CHANNEL_ELECTRONIC)
        ->and($bid->submitted_at)->not->toBeNull()
        ->and($bid->receipt_no)->toStartWith('BAC-')
        ->and($bid->documents)->toHaveCount(count(BidSubmissionRequirements::for($project)->requiredKeys()))
        ->and($bid->documents->every(fn ($document) => strlen((string) $document->sha256) === 64
            && ($document->component === BidDocument::COMPONENT_FINANCIAL
                ? str_starts_with($document->file_path, "bid-financial/{$project->id}/{$bid->id}/") && $document->encrypted_at !== null
                : str_starts_with($document->file_path, "bid-submissions/{$project->id}/{$bid->id}/technical/"))))->toBeTrue();

    $event = BidTracking::where('bid_id', $bid->id)->where('decision', 'submitted')->firstOrFail();
    expect($event->visible_to_bidder)->toBeTrue()
        ->and($event->details['receipt_no'])->toBe($bid->receipt_no)
        ->and($event->created_by)->toBe($this->bidder->id);

    expect($bid->progress()->adminStatus()['label'])->toBe('Submitted');
    $track = ($this->track)();
    expect($track['current']['label'])->toBe('Submitted - Awaiting Bid Opening')
        ->and(collect($track['history'])->pluck('title'))->toContain('Bid Submitted');

    // A second submission is a modification (Sec. 55.1); later stages come only from BAC actions.
    ($this->submit)($project, ['bid_amount' => '2300000'])->assertSessionHasNoErrors();
    expect($bid->fresh()->documents_validated_at)->toBeNull()
        ->and($bid->fresh()->receipt_no)->toBe($bid->receipt_no.'-M1');
});

it('lets a bidder modify an online bid before the deadline and keeps the original on record', function () {
    $project = ($this->makeProject)();
    ($this->submit)($project, ['bid_amount' => '2400000', 'documents' => ($this->allRequiredFiles)($project)])->assertSessionHasNoErrors();
    $bid = Bid::with('documents')->firstOrFail();
    $original = ['receipt' => $bid->receipt_no, 'at' => $bid->submitted_at];
    $oldForm = $bid->documents->firstWhere('requirement_key', 'financial_bid_form');

    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertSee('Modify bid')
        ->assertSee('Modify Your Bid')
        ->assertSee('Modifying Receipt No. '.$original['receipt'])
        ->assertSee('Submit Modification')
        ->assertSee('kept unless you choose a replacement');

    $this->travel(2)->hours();

    // Only the replaced file is uploaded; the rest carry over.
    ($this->submit)($project, [
        'bid_amount' => '2,350,000.50',
        'documents' => ['financial_bid_form' => ($this->pdf)('revised-financial-bid.pdf')],
    ])->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'Bid modification received') && str_contains($message, $original['receipt'].'-M1'));

    $bid->refresh()->load('documents');
    expect(Bid::count())->toBe(1)
        ->and($bid->bid_amount)->toEqual('2350000.50')
        ->and($bid->receipt_no)->toBe($original['receipt'].'-M1')
        ->and($bid->submitted_at->gt($original['at']))->toBeTrue()
        ->and($bid->documents)->toHaveCount(count(BidSubmissionRequirements::for($project)->requiredKeys()))
        ->and($bid->documents->firstWhere('requirement_key', 'financial_bid_form')->original_name)->toBe('revised-financial-bid.pdf');

    // The superseded file is never deleted, and the modification is labelled on the record.
    Storage::disk('local')->assertExists($oldForm->file_path);
    $event = BidTracking::where('bid_id', $bid->id)->where('decision', 'modified')->firstOrFail();
    expect($event->status_title)->toBe('Bid Modification Received')
        ->and($event->details['previous_receipt_no'])->toBe($original['receipt'])
        ->and($event->details['replaced_files'][0]['file'])->toBe($oldForm->original_name)
        ->and($event->details['replaced_files'][0]['sha256'])->toBe($oldForm->sha256)
        ->and($event->details)->not->toHaveKey('replaced_files.0.path');

    // A second modification gets the next label; the new password applies.
    ($this->submit)($project, [
        'bid_amount' => '2340000',
        'financial_password' => '730145',
        'financial_password_confirmation' => '730145',
    ])->assertSessionHasNoErrors();
    expect($bid->fresh()->receipt_no)->toBe($original['receipt'].'-M2')
        ->and(Hash::check('730145', $bid->fresh()->financial_opening_password_hash))->toBeTrue();
});

it('refuses a bid modification after the deadline or once the BAC acted on the bid', function () {
    $project = ($this->makeProject)();
    ($this->submit)($project, ['bid_amount' => '2400000', 'documents' => ($this->allRequiredFiles)($project)])->assertSessionHasNoErrors();
    $bid = Bid::firstOrFail();
    $receipt = $bid->receipt_no;

    // The BAC has started on the bid: no longer modifiable, even before the deadline.
    $bid->update(['workflow_step' => Bid::STEP_DOCUMENTS_VALIDATED]);
    ($this->submit)($project, ['bid_amount' => '2300000'])->assertSessionHasErrors('bid');
    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))->assertDontSee('Modify bid');
    $bid->update(['workflow_step' => Bid::STEP_SUBMITTED]);

    $this->travel(5)->days();
    $this->travel(1)->minutes();

    ($this->submit)($project, ['bid_amount' => '2300000'])->assertSessionHasErrors('deadline');
    expect($bid->fresh()->receipt_no)->toBe($receipt)
        ->and($bid->fresh()->bid_amount)->toEqual('2400000.00')
        ->and(BidTracking::where('decision', 'modified')->count())->toBe(0);
});

it('keeps technical and financial components sealed separately', function () {
    $project = ($this->makeProject)();
    ($this->authorizeElectronic)($project);
    ($this->submit)($project, ['bid_amount' => '2400000', 'documents' => ($this->allRequiredFiles)($project)]);
    $bid = Bid::with('documents')->firstOrFail();
    $technical = $bid->documents->firstWhere('component', BidDocument::COMPONENT_TECHNICAL);
    $financial = $bid->documents->firstWhere('component', BidDocument::COMPONENT_FINANCIAL);
    $open = fn (BidDocument $document) => testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', ['bid' => $bid, 'bidDocument' => $document]));

    $open($technical)->assertForbidden();
    $open($financial)->assertForbidden();

    testCase()->actingAs($this->admin)->post(route('admin.project.bid-opening-rules', $project), [
        'award_criterion' => 'lowest_calculated_bid',
        'opening_documents_reference' => 'Section III, ITB Clause 26',
    ])->assertSessionHasNoErrors();

    $this->travel(6)->days();
    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project))->assertSessionHasNoErrors();

    $open($technical)->assertOk();
    $open($financial)->assertForbidden();
    testCase()->actingAs($this->admin)->get(route('admin.bids'))->assertDontSee('2,400,000.00');

    // Passing preliminary examination: the checklist is the same submission checklist.
    testCase()->actingAs($this->admin)->post(route('admin.bid.decision', $bid), [
        'action' => BidWorkflow::PASS_PRELIMINARY,
        'verified_requirements' => BidSubmissionRequirements::for($project)->requiredKeys(),
    ])->assertSessionHasNoErrors();

    testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $bid), [
        'opening_password' => '482913',
    ])->assertSessionHasNoErrors();

    $open($financial)->assertOk();
    testCase()->actingAs($this->admin)->get(route('admin.bids'))->assertSee('2,400,000.00');
});

it('saves the online submission settings with an optional LGU authority', function () {
    $project = ($this->makeProject)();

    // The project is already published: setting its fee is a recorded amendment.
    testCase()->actingAs($this->admin)->post(route('admin.project.submission-settings', $project), [
        'bidding_documents_fee' => '5000',
        'payment_venue' => 'BAC Secretariat, 2F Municipal Hall',
        'bidding_fee_amendment_reference' => 'Supplemental Bid Bulletin No. 1',
        'bidding_fee_amendment_reason' => 'Fee from the ABC schedule added to the notice.',
    ])->assertSessionHasNoErrors();

    $project->refresh();
    expect($project->bidding_documents_fee)->toBe('5000.00')
        ->and($project->requiresBiddingFee())->toBeTrue()
        ->and($project->payment_venue)->toBe('BAC Secretariat, 2F Municipal Hall')
        ->and($project->electronic_submission_authority)->toBeNull()
        ->and($project->electronic_submission_authorized_at)->toBeNull();

    testCase()->actingAs($this->admin)->post(route('admin.project.submission-settings', $project), [
        'bidding_documents_fee' => '5000.00',
        'electronic_submission_authority' => 'BAC Resolution No. 2026-014',
    ])->assertSessionHasNoErrors();

    $project->refresh();
    expect($project->electronic_submission_authority)->toBe('BAC Resolution No. 2026-014')
        ->and($project->electronic_submission_authorized_by)->toBe($this->admin->id);

    // Free documents only through an explicit, reasoned waiver (here also an amendment).
    testCase()->actingAs($this->admin)->post(route('admin.project.submission-settings', $project), [
        'bidding_fee_mode' => 'waived',
        'bidding_fee_reason' => 'Waived by BAC Resolution No. 2026-021.',
        'bidding_fee_amendment_reference' => 'Supplemental Bid Bulletin No. 2',
        'bidding_fee_amendment_reason' => 'The BAC waived the fee before any payment.',
    ])->assertSessionHasNoErrors();
    expect($project->fresh()->requiresBiddingFee())->toBeFalse();
});

it('does not present an unrelated uploaded file as an official bidding document', function () {
    $project = ($this->makeProject)();
    ProjectDocument::create(['project_id' => $project->id, 'original_name' => 'Invitation to Bid.pdf', 'file_path' => 'project-documents/project_doc_'.$project->id.'_20260901_0.pdf', 'document_type' => 'invitation_to_bid']);
    ProjectDocument::create(['project_id' => $project->id, 'original_name' => 'bulletin-1.pdf', 'file_path' => 'project-documents/project_doc_'.$project->id.'_20260902_1.pdf', 'document_type' => 'supplemental_bulletin']);
    ProjectDocument::create(['project_id' => $project->id, 'original_name' => 'proposal_7_3_20260915.pdf', 'file_path' => 'proposals/proposal_7_3_20260915.pdf']);
    ProjectDocument::create(['project_id' => $project->id, 'original_name' => 'other-project.pdf', 'file_path' => 'project-documents/project_doc_999_20260101_0.pdf']);

    expect($project->officialDocuments()->pluck('original_name')->all())->toBe(['Invitation to Bid.pdf', 'bulletin-1.pdf'])
        ->and($project->unverifiedDocuments())->toHaveCount(2);

    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertSee('Supplemental Bid Bulletins')
        ->assertSee('bulletin-1.pdf')
        ->assertDontSee('proposal_7_3_20260915.pdf')
        ->assertDontSee('other-project.pdf')
        ->assertSee('2 files linked to this project could not be verified');

    // Only official documents can be opened by index.
    testCase()->actingAs($this->bidder)->get(route('bidder.project.document.preview', ['project' => $project, 'document' => 2]))->assertNotFound();

    testCase()->actingAs($this->admin)->get(route('admin.project.view', $project), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertSee('could not be verified as this project')
        ->assertSee('proposal_7_3_20260915.pdf');
});

it('treats a receipt-backed electronic bid as officially submitted when its legacy timestamp is missing', function () {
    $project = ($this->makeProject)();
    $bid = Bid::create([
        'project_id' => $project->id,
        'user_id' => $this->bidder->id,
        'bid_amount' => 2000000,
        'status' => 'pending',
        'workflow_step' => Bid::STEP_SUBMITTED,
        'submission_channel' => Bid::CHANNEL_ELECTRONIC,
        'receipt_no' => 'BAC-2026-0001-M1',
        'submitted_at' => null,
    ]);

    expect($bid->isOfficiallySubmitted())->toBeTrue()
        ->and($bid->isDraft())->toBeFalse()
        ->and($bid->progress()->adminStatus()['label'])->toBe('Submitted');
});
it('keeps bids submitted before submission channels existed as submitted', function () {
    $project = ($this->makeProject)();
    $legacy = Bid::create(['project_id' => $project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 2000000, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED]);

    expect($legacy->isOfficiallySubmitted())->toBeTrue()
        ->and($legacy->progress()->adminStatus()['label'])->toBe('Submitted');

    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertSee('Bid submitted')
        ->assertDontSee('Continue draft');
});
