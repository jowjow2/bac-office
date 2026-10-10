<?php

use App\Events\BidWorkflowUpdated;
use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    Event::fake([BidWorkflowUpdated::class]);
    $this->admin = User::create(['name' => 'Admin Reviewer', 'email' => 'bid-admin@example.com', 'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'active']);
    $this->bidder = User::create(['name' => 'Bidder', 'email' => 'bid-builder@example.com', 'password' => bcrypt('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Example Builders']);
    $this->project = Project::create([
        'title' => 'Road Repair', 'description' => 'Repair works', 'budget' => 100000,
        'deadline' => now()->addWeek(), 'status' => 'open',
    ]);
    $this->makeBid = fn (array $attributes = []) => Bid::create(array_merge([
        'user_id' => $this->bidder->id, 'project_id' => $this->project->id,
        'bid_amount' => 95000, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED,
    ], $attributes));
});

it('renders readable document status and receipt separators in the bid list', function () {
    ($this->makeBid)();

    $this->actingAs($this->admin)
        ->get(route('admin.bids'))
        ->assertOk()
        ->assertDontSee('Ã¢')
        ->assertDontSee('â€');
});

it('renders the project reference and title with a readable separator in the bid review modal', function () {
    $this->project->update(['reference_no' => 'SJ-BAC-TEST-001']);
    $bid = ($this->makeBid)();

    $this->actingAs($this->admin)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('admin.bid.view', $bid))
        ->assertOk()
        ->assertSee('SJ-BAC-TEST-001 - Road Repair');
});
it('paginates bids with stable ordering and preserves filters', function () {
    for ($i = 0; $i < 47; $i++) ($this->makeBid)();
    $response = $this->actingAs($this->admin)->get(route('admin.bids', [
        'search' => 'Road', 'status' => 'pending', 'per_page' => 10, 'page' => 2,
    ]));
    $response->assertOk()
        ->assertSee('bid-pagination-nav', false)
        ->assertSee('aria-current="page">2</span>', false)
        ->assertSee('>1</a>', false)
        ->assertSee('>3</a>', false)
        ->assertDontSee('Rows per page')
        ->assertDontSee('Page 2 of');
    $bids = $response->viewData('bids');
    expect($bids->count())->toBe(10)
        ->and($bids->total())->toBe(47)
        ->and($bids->nextPageUrl())->toContain('search=Road', 'status=pending', 'per_page=10', 'page=3');
    $response->assertDontSee('data-bid-select', false)->assertSee('bidExportModal')->assertSee('Review')->assertSee('Submitted');
    $this->get(route('admin.bids', ['page' => 99]))->assertRedirect(route('admin.bids', ['page' => 5]));
});

it('shows an accurate empty state', function () {
    $this->actingAs($this->admin)->get(route('admin.bids'))
        ->assertOk()->assertDontSee('bid-pagination')->assertSee('No bids found');
});
it('flags only variance outside the strict thresholds and handles zero budgets', function ($amount, $budget, $warning) {
    $this->project->update(['budget' => $budget]);
    $bid = ($this->makeBid)(['bid_amount' => $amount]);
    // The variance reveals the price, so it shows only after both recorded openings.
    $this->project->forceFill(['bids_opened_at' => now(), 'bids_opened_by' => $this->admin->id])->save();
    $bid->forceFill(['documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id, 'financial_opened_at' => now(), 'financial_opened_by' => $this->admin->id])->save();
    $html = Blade::render('<x-bid-variance :bid="$bid" />', ['bid' => $bid->load('project')]);
    expect(str_contains($html, 'Unusual variance'))->toBe($warning);
    if ($budget === 0) expect($html)->toContain('N/A');
})->with([
    [600001, 100000, true], [600000, 100000, false],
    [9999, 100000, true], [10000, 100000, false], [95000, 100000, false], [95000, 0, false],
]);

it('combines proposal preview, details and stage decisions in the modal', function () {
    $bid = ($this->makeBid)(['proposal_file' => 'proposals/example.pdf', 'documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id]);
    $bid->forceFill(['financial_opened_at' => now(), 'financial_opened_by' => $this->admin->id])->save();
    // Proposals are previewable only after the recorded bid opening.
    $this->project->update(['deadline' => now()->subDay(), 'status' => 'closed', 'bids_opened_at' => now()]);
    $this->project->forceFill(['award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'ITB clause 24'])->save();
    $this->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('Review Bid')
        ->assertSee('bid-proposal-preview')->assertSee(route('admin.bid.decision', ['bid' => $bid], false), false)
        ->assertSee('Start Detailed Evaluation');
    $bid->update([
        'status' => 'awarded',
        'workflow_step' => Bid::STEP_AWARDED,
        'award_decision' => Bid::AWARD_DECISION_APPROVED,
        'award_decision_at' => now(),
        'evaluated_at' => now(),
        'post_qualification_at' => now(),
        'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED,
        'post_qualification_completed_at' => now(),
        'bac_recommended_at' => now(),
        'notice_of_award_at' => now(),
    ]);
    $this->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('fa-trophy')
        ->assertDontSee('Failed Preliminary Examination');
});

it('rejects bulk approval because decisions are recorded one bid at a time', function () {
    $first = ($this->makeBid)();
    $this->actingAs($this->admin)->post(route('admin.bids.bulk'), [
        'action' => 'approve', 'ids' => [$first->id],
    ])->assertSessionHasErrors('action');
    expect($first->fresh()->status)->toBe('pending')
        ->and($first->fresh()->documents_validated_at)->toBeNull();
    Event::assertNotDispatched(BidWorkflowUpdated::class);
});

it('validates selections and limits bulk actions to admins', function () {
    $bid = ($this->makeBid)();
    $this->actingAs($this->bidder)->post(route('admin.bids.bulk'), [
        'action' => 'export', 'ids' => [$bid->id],
    ])->assertForbidden();
    $this->actingAs($this->admin)->post(route('admin.bids.bulk'), ['action' => 'export', 'ids' => []])->assertSessionHasErrors('ids');
    $this->post(route('admin.bids.bulk'), ['action' => 'export', 'ids' => [$bid->id, $bid->id]])->assertSessionHasErrors('ids.0');
    $this->post(route('admin.bids.bulk'), ['action' => 'export', 'ids' => [999999]])->assertSessionHasErrors('ids.0');
});

it('exports exactly the selection and escapes spreadsheet formulas', function () {
    $this->bidder->update(['company' => '=SUM(1,2)']);
    $selected = ($this->makeBid)();
    ($this->makeBid)();

    // Before the bid opening the amount and variance stay sealed in exports too.
    $sealedRow = str_getcsv(preg_split('/\r?\n/', trim($this->actingAs($this->admin)->post(route('admin.bids.bulk'), [
        'action' => 'export', 'ids' => [$selected->id],
    ])->streamedContent()))[1], ',', '"', '');
    expect($sealedRow[4])->toBe('Sealed')->and($sealedRow[6])->toBe('')->and($sealedRow[8])->toBe('Submitted');

    // Opened, but the financial component stays sealed until preliminary examination is passed.
    $this->project->update(['deadline' => now()->subDay(), 'status' => 'closed', 'bids_opened_at' => now()]);
    $this->project->forceFill(['award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'ITB clause 24'])->save();
    $openedRow = str_getcsv(preg_split('/\r?\n/', trim($this->post(route('admin.bids.bulk'), [
        'action' => 'export', 'ids' => [$selected->id],
    ])->streamedContent()))[1], ',', '"', '');
    expect($openedRow[4])->toBe('Sealed')->and($openedRow[8])->toBe('Preliminary Examination');

    $this->project->forceFill(['award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'ITB clause 24'])->save();
    $selected->update([
        'documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id,
        'eligibility_status' => Bid::ELIGIBILITY_VALID, 'financial_opening_password_hash' => bcrypt('123456'),
    ]);
    $this->actingAs($this->admin)->post(route('admin.bid.open-financial', $selected), ['opening_password' => '123456'])->assertSessionHasNoErrors();
    $response = $this->post(route('admin.bids.bulk'), [
        'action' => 'export', 'ids' => [$selected->id],
    ]);
    $response->assertDownload('selected-bids.csv');
    $lines = preg_split('/\r?\n/', trim($response->streamedContent()));
    expect($lines)->toHaveCount(2);
    $row = str_getcsv($lines[1], ',', '"', '');
    expect($row[0])->toBe((string) $selected->id)
        ->and($row[1])->toBe("'=SUM(1,2)")
        ->and($row[6])->toBe('-5')
        ->and($row[8])->toBe('Bid Evaluation')
        ->and($selected->fresh()->status)->toBe('pending');
});

it('records each stage decision once and protects closed bids', function () {
    $this->project->update(['deadline' => now()->subDay(), 'status' => 'closed', 'bids_opened_at' => now()]);
    $this->project->forceFill(['award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'ITB clause 24'])->save();
    $bid = ($this->makeBid)(['proposal_file' => 'proposals/example.pdf']);
    $this->project->requirement()->create(['required_documents' => ['Financial Proposal']]);

    $pass = fn (Bid $target) => $this->actingAs($this->admin)->post(route('admin.bid.decision', $target), [
        'action' => 'pass_preliminary', 'verified_requirements' => ['financial_proposal'],
    ]);

    $pass($bid)->assertSessionHasNoErrors();
    $pass($bid)->assertSessionHasErrors('milestone');

    $failed = ($this->makeBid)(['proposal_file' => 'proposals/other.pdf']);
    $this->post(route('admin.bid.decision', $failed), ['action' => 'fail_preliminary', 'reason' => 'Unsigned financial proposal.'])
        ->assertSessionHasNoErrors();
    $pass($failed)->assertSessionHasErrors('milestone');
    expect($failed->fresh()->progress()->adminStatus()['label'])->toBe('Disqualified');
    Event::assertDispatchedTimes(BidWorkflowUpdated::class, 2);
});

it('keeps Proceed disabled at the scheduled opening without advancing the bid', function () {
    $bid = ($this->makeBid)();
    $response = $this->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest']);
    $response->assertOk()->assertSee('Next step: Wait for scheduled opening')
        ->assertSee('Bids cannot be opened before the submission deadline');
    expect($response->getContent())->toMatch('/data-br-proceed="technical-opening"[^>]*disabled/');
    expect($bid->fresh()->documents_validated_at)->toBeNull();
    Event::assertNotDispatched(BidWorkflowUpdated::class);
});

it('opens the current evaluation form through Proceed without recording a decision', function () {
    $this->project->forceFill(['deadline' => now()->subDay(), 'bids_opened_at' => now(), 'bids_opened_by' => $this->admin->id])->save();
    $bid = ($this->makeBid)([
        'documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id,
        'financial_opened_at' => now(), 'financial_opened_by' => $this->admin->id,
        'bac_evaluation_at' => now(), 'workflow_step' => Bid::STEP_FOR_BAC_EVALUATION,
    ]);
    $bid->forceFill(['documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id, 'financial_opened_at' => now(), 'financial_opened_by' => $this->admin->id])->save();
    $response = $this->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest']);
    $response->assertOk()->assertSee('Evaluation Actions')
        ->assertSee('Next step: Record Evaluation Result')
        ->assertSee('data-br-action="evaluate" data-br-next-label="Record Evaluation Result"', false)
        ->assertDontSee('Confirm: Record Detailed Evaluation')
        ->assertSee('name="evaluation_findings"', false);
    expect($response->getContent())->toMatch('/type="button"[^>]*data-br-proceed="evaluate"[^>]*(?<!disabled)>/');
    expect($bid->fresh()->evaluated_at)->toBeNull();
    Event::assertNotDispatched(BidWorkflowUpdated::class);
});

it('keeps award recommendation hidden and blocked until all earlier stages are complete', function () {
    $this->project->forceFill(['deadline' => now()->subDay(), 'bids_opened_at' => now(), 'bids_opened_by' => $this->admin->id])->save();
    $bid = ($this->makeBid)(['post_qualification_result' => Bid::POST_QUALIFICATION_PASSED]);

    $this->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Next step: Passed Preliminary Examination')
        ->assertSee('data-br-proceed="pass_preliminary"', false)
        ->assertDontSee('data-br-action="recommend"', false);

    $this->post(route('admin.bid.decision', ['bid' => $bid], false), ['action' => 'recommend'])
        ->assertSessionHasErrors('milestone');
    expect($bid->fresh()->bac_recommended_at)->toBeNull();
});

it('keeps financial opening ahead of stale award approval fields', function () {
    $this->project->forceFill([
        'deadline' => now()->subDay(), 'bids_opened_at' => now(), 'bids_opened_by' => $this->admin->id,
        'award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'ITB clause 24',
    ])->save();
    $bid = ($this->makeBid)([
        'documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id,
        'financial_opening_password_hash' => bcrypt('123456'),
        // Simulate stale downstream fields that must not skip financial review.
        'evaluated_at' => now(), 'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED,
        'award_decision' => Bid::AWARD_DECISION_APPROVED,
    ]);

    $html = $this->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Next step: Verify Financial Password')
        ->assertSee('data-br-proceed="financial-opening"', false)
        ->assertDontSee('Next step: Issue Notice of Award')
        ->assertDontSee('data-br-action="notice_of_award"', false)
        ->getContent();

    expect($bid->progress()->adminStatus()['label'])->toBe('Bid Evaluation')
        ->and($html)->toContain('Financial Password')
        ->and($bid->fresh()->financial_opened_at)->toBeNull();

    $this->post(route('admin.bid.decision', ['bid' => $bid], false), ['action' => 'notice_of_award'])
        ->assertSessionHasErrors('milestone');
    expect($bid->fresh()->financial_opened_at)->toBeNull();
});

it('retains the financial PIN form as the next action after preliminary examination', function () {
    $this->project->forceFill([
        'deadline' => now()->subDay(), 'bids_opened_at' => now(), 'bids_opened_by' => $this->admin->id,
        'award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'ITB clause 24',
    ])->save();
    $bid = ($this->makeBid)([
        'documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id,
        'financial_opening_password_hash' => bcrypt('123456'),
    ]);
    $this->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('Next step: Verify Financial Password')
        ->assertSee('data-br-action="financial-opening"', false)
        ->assertSee('name="opening_password"', false)->assertSee('data-br-password-toggle', false);
    expect($bid->fresh()->financial_opened_at)->toBeNull();
});

it('disables Proceed for missing requirements while retaining the failure decision', function () {
    $this->project->forceFill(['deadline' => now()->subDay(), 'bids_opened_at' => now(), 'bids_opened_by' => $this->admin->id])->save();
    $bid = ($this->makeBid)();
    $response = $this->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest']);
    $response->assertOk()->assertSee('Missing required documents:')
        ->assertSee('data-br-action="fail_preliminary"', false);
    expect($response->getContent())->toMatch('/data-br-proceed="pass_preliminary"[^>]*disabled/');
});

it('selects the next recorded stage for Proceed including historical awards', function ($attributes, $action) {
    $this->project->forceFill(['deadline' => now()->subDay(), 'bids_opened_at' => now(), 'bids_opened_by' => $this->admin->id])->save();
    $bid = ($this->makeBid)();
    $bid->forceFill([
        'documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id,
        'financial_opened_at' => now(), 'financial_opened_by' => $this->admin->id,
        'bac_evaluation_at' => now(), 'evaluated_at' => now(),
    ] + $attributes())->save();
    $response = $this->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest']);
    $response->assertOk()->assertSee('data-br-proceed="'.$action.'"', false);
    if ($action === '') expect($response->getContent())->toMatch('/data-br-proceed=""[^>]*disabled/');
})->with([
    'evaluated' => [fn () => [], 'start_post_qualification'],
    'post-qualification' => [fn () => ['post_qualification_at' => now()], 'pass_post_qualification'],
    'post-qualified' => [fn () => ['post_qualification_at' => now(), 'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED], 'recommend'],
    'recommended' => [fn () => ['post_qualification_at' => now(), 'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED, 'bac_recommended_at' => now()], 'approve_award'],
    'approved historical award' => [fn () => ['post_qualification_at' => now(), 'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED, 'bac_recommended_at' => now(), 'status' => 'awarded', 'award_decision' => Bid::AWARD_DECISION_APPROVED], 'notice_of_award'],
    'notice issued' => [fn () => ['post_qualification_at' => now(), 'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED, 'bac_recommended_at' => now(), 'status' => 'awarded', 'award_decision' => Bid::AWARD_DECISION_APPROVED, 'notice_of_award_at' => now()], 'contract_signed'],
    'contract signed' => [fn () => ['post_qualification_at' => now(), 'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED, 'bac_recommended_at' => now(), 'status' => 'awarded', 'award_decision' => Bid::AWARD_DECISION_APPROVED, 'notice_of_award_at' => now(), 'contract_signed_at' => now()], 'notice_to_proceed'],
    'complete' => [fn () => ['post_qualification_at' => now(), 'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED, 'bac_recommended_at' => now(), 'status' => 'awarded', 'award_decision' => Bid::AWARD_DECISION_APPROVED, 'notice_of_award_at' => now(), 'contract_signed_at' => now(), 'notice_to_proceed_at' => now()], ''],
    'disqualified' => [fn () => ['disqualified_at' => now()], ''],
]);
