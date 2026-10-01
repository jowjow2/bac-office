<?php

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BiddingFeeAmendment;
use App\Models\BiddingFeePayment;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidSubmissionRequirements;
use App\Support\BiddingDocumentsFee;
use App\Support\BidWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Bidding documents fee of competitive bidding from the approved ABC
 * (GPPB Circular No. 02-2026, Sec. 5.2: maximum rates), its waiver, its
 * amendment after publication, and the BAC-verified payment check.
 */
beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');

    $user = fn (string $email, string $role, array $extra = []) => User::create(array_merge([
        'name' => ucfirst($role).' User', 'email' => $email, 'password' => Hash::make('password'), 'role' => $role, 'status' => 'active',
    ], $extra));
    $this->admin = $user('fee-sched-admin@example.com', 'admin');
    $this->bidder = $user('fee-sched-bidder@example.com', 'bidder', ['company' => 'Mindoro Builders']);

    // A published competitive bidding with the fee from its ABC.
    $this->makeProject = function (array $attributes = [], ?string $mode = BiddingDocumentsFee::MODE_SCHEDULE, ?string $reason = null): Project {
        $project = Project::create(array_merge([
            'title' => 'Road Concreting', 'description' => 'Concreting works.', 'reference_no' => 'SJOM-2026-I-'.random_int(100, 999),
            'category' => 'infrastructure', 'procurement_mode' => 'public_bidding', 'budget' => 2500000,
            'deadline' => now()->addDays(5), 'status' => 'open', 'published_at' => now()->subDay(),
            'payment_venue' => 'BAC Secretariat, 2F Municipal Hall',
        ], $attributes));
        $project->forceFill(BiddingDocumentsFee::resolve($project, array_filter(['bidding_fee_mode' => $mode, 'bidding_fee_reason' => $reason, 'bidding_documents_fee' => $attributes['fee'] ?? null])))->save();
        ProjectSchedule::create([
            'project_id' => $project->id, 'date_posted' => now()->subDay()->toDateString(),
            'bid_submission_deadline' => $project->deadline, 'bid_opening_date' => $project->deadline->copy()->addHour(),
        ]);

        return $project->fresh();
    };

    $this->submitBid = function (Project $project, string $amount = '2400000') {
        $files = collect(BidSubmissionRequirements::for($project)->requiredKeys())
            ->mapWithKeys(fn (string $key) => [$key => UploadedFile::fake()->createWithContent($key.'.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF")])
            ->all();

        return testCase()->actingAs($this->bidder)->post(route('bidder.bids.store', $project), [
            'project_id' => $project->id, 'bid_amount' => $amount, 'documents' => $files,
            'financial_password' => '482913', 'financial_password_confirmation' => '482913',
        ]);
    };

    $this->pay = fn (Project $project, array $attributes = []) => BiddingFeePayment::create(array_merge([
        'project_id' => $project->id, 'user_id' => $this->bidder->id, 'amount' => $project->bidding_documents_fee,
        'or_number' => 'OR-'.random_int(100000, 999999), 'status' => BiddingFeePayment::STATUS_VERIFIED,
        'paid_at' => now()->toDateString(), 'verified_at' => now(), 'recorded_by' => $this->admin->id,
    ], $attributes));

    $this->edit = fn (Project $project, array $data) => testCase()->actingAs($this->admin)->put(route('admin.project.update', $project), array_merge([
        'title' => $project->title, 'description' => $project->description, 'budget' => (string) $project->budget,
        'status' => $project->status, 'deadline' => $project->deadline->format('Y-m-d\TH:i'),
    ], $data));
});

it('takes the maximum fee from the ABC bracket, boundaries included', function (string $abc, ?string $fee) {
    expect(BiddingDocumentsFee::maximumFor($abc))->toBe($fee);
})->with([
    'no ABC' => ['0', null],
    '500,000' => ['500000', '500.00'],
    '500,000.01' => ['500000.01', '1000.00'],
    '1M' => ['1000000', '1000.00'],
    '1M + 0.01' => ['1000000.01', '5000.00'],
    '5M' => ['5000000', '5000.00'],
    '5M + 0.01' => ['5000000.01', '10000.00'],
    '10M' => ['10000000', '10000.00'],
    '10M + 0.01' => ['10000000.01', '25000.00'],
    '50M' => ['50000000', '25000.00'],
    '50M + 0.01' => ['50000000.01', '50000.00'],
    '500M' => ['500000000', '50000.00'],
    '500M + 0.01' => ['500000000.01', '75000.00'],
    'largest ABC' => ['9999999999999.99', '75000.00'],
]);

it('fills the fee from the ABC in the wizard and allows only a lower fee or a waiver with a reason', function () {
    $draft = fn (array $data) => testCase()->actingAs($this->admin)->post(route('admin.projects.wizard.store'), array_merge([
        'status' => 'draft', 'title' => 'Drainage Works', 'procurement_mode' => 'public_bidding', 'category' => 'infrastructure', 'budget' => '2500000',
    ], $data));

    $draft([])->assertSessionHasNoErrors();
    $project = Project::latest('id')->firstOrFail();
    expect($project->bidding_documents_fee)->toBe('5000.00')->and($project->bidding_fee_mode)->toBe('schedule');

    // Never above the maximum; lower or waived only with a reason.
    $draft(['bidding_fee_mode' => 'reduced', 'bidding_documents_fee' => '6000', 'bidding_fee_reason' => 'Printing cost of the plans.'])->assertSessionHasErrors('bidding_documents_fee');
    $draft(['bidding_documents_fee' => '6000'])->assertSessionHasErrors('bidding_documents_fee');
    $draft(['bidding_fee_mode' => 'reduced', 'bidding_documents_fee' => '2000'])->assertSessionHasErrors('bidding_fee_reason');
    $draft(['bidding_fee_mode' => 'waived'])->assertSessionHasErrors('bidding_fee_reason');
    expect(Project::count())->toBe(1);

    $draft(['bidding_fee_mode' => 'reduced', 'bidding_documents_fee' => '2000', 'bidding_fee_reason' => 'Per BAC Resolution No. 2026-020.'])->assertSessionHasNoErrors();
    $reduced = Project::latest('id')->firstOrFail();
    expect($reduced->bidding_documents_fee)->toBe('2000.00')->and($reduced->bidding_fee_mode)->toBe('reduced')
        ->and($reduced->bidding_fee_reason)->toBe('Per BAC Resolution No. 2026-020.');

    $draft(['bidding_fee_mode' => 'waived', 'bidding_fee_reason' => 'Waived by BAC Resolution No. 2026-021.'])->assertSessionHasNoErrors();
    $waived = Project::latest('id')->firstOrFail();
    expect($waived->bidding_documents_fee)->toBe('0.00')->and($waived->requiresBiddingFee())->toBeFalse()->and($waived->biddingFeeWaived())->toBeTrue();

    // Not competitive bidding: no schedule, the fee stays as entered.
    $draft(['procurement_mode' => 'small_value_procurement', 'budget' => '400000', 'bidding_documents_fee' => ''])->assertSessionHasNoErrors();
    $svp = Project::latest('id')->firstOrFail();
    expect($svp->bidding_documents_fee)->toBeNull()->and($svp->bidding_fee_mode)->toBeNull();
});

it('follows a draft ABC change and keeps a lower fee within the new maximum', function () {
    $project = ($this->makeProject)(['status' => 'draft', 'published_at' => null]);
    expect($project->bidding_documents_fee)->toBe('5000.00');

    ($this->edit)($project, ['budget' => '12,000,000.00'])->assertSessionHasNoErrors();
    expect($project->fresh()->bidding_documents_fee)->toBe('25000.00');

    ($this->edit)($project->fresh(), ['bidding_fee_mode' => 'reduced', 'bidding_documents_fee' => '8,000.00', 'bidding_fee_reason' => 'Per BAC Resolution No. 2026-022.'])->assertSessionHasNoErrors();
    // A smaller ABC whose maximum is below the lower fee is refused, not silently raised.
    ($this->edit)($project->fresh(), ['budget' => '900000'])->assertSessionHasErrors('bidding_documents_fee');
    expect($project->fresh()->bidding_documents_fee)->toBe('8000.00');
});

it('changes a published fee only through a recorded amendment, and never after a payment', function () {
    $project = ($this->makeProject)();

    ($this->edit)($project, ['bidding_fee_mode' => 'waived', 'bidding_fee_reason' => 'Waived by BAC Resolution No. 2026-023.'])
        ->assertSessionHasErrors(['bidding_fee_amendment_reference', 'bidding_fee_amendment_reason']);
    // A new ABC that moves the fee is a change too.
    ($this->edit)($project, ['budget' => '6000000'])->assertSessionHasErrors('bidding_fee_amendment_reference');
    // A new ABC in the same bracket leaves the published fee alone.
    ($this->edit)($project, ['budget' => '2600000'])->assertSessionHasNoErrors();
    expect($project->fresh()->bidding_documents_fee)->toBe('5000.00')->and(BiddingFeeAmendment::count())->toBe(0);

    ($this->edit)($project->fresh(), [
        'bidding_fee_mode' => 'reduced', 'bidding_documents_fee' => '3000', 'bidding_fee_reason' => 'Per BAC Resolution No. 2026-024.',
        'bidding_fee_amendment_reference' => 'Supplemental Bid Bulletin No. 1', 'bidding_fee_amendment_reason' => 'Fee lowered for small contractors.',
    ])->assertSessionHasNoErrors();

    $amendment = BiddingFeeAmendment::firstOrFail();
    expect($project->fresh()->bidding_documents_fee)->toBe('3000.00')
        ->and($amendment->previous_fee)->toBe('5000.00')->and($amendment->new_fee)->toBe('3000.00')
        ->and($amendment->reference)->toBe('Supplemental Bid Bulletin No. 1')->and($amendment->amended_by)->toBe($this->admin->id)
        ->and(AuditLog::where('action', 'bidding_fee_amended')->exists())->toBeTrue();

    // The Invitation to Bid shows how the fee was set and its amendment.
    testCase()->get(route('public.procurement.show', $project))->assertOk()
        ->assertSee('₱3,000.00, lower than the ₱5,000.00 maximum for an ABC above ₱1 million up to ₱5 million (GPPB Circular No. 02-2026, Sec. 5.2).', false)
        ->assertSee('Amended by Supplemental Bid Bulletin No. 1');

    ($this->pay)($project->fresh());
    ($this->edit)($project->fresh(), [
        'bidding_fee_mode' => 'schedule', 'bidding_fee_amendment_reference' => 'Supplemental Bid Bulletin No. 2', 'bidding_fee_amendment_reason' => 'Back to the maximum.',
    ])->assertSessionHasErrors('bidding_documents_fee');
    expect($project->fresh()->bidding_documents_fee)->toBe('3000.00');
});

it('accepts an online bid only after a BAC-verified payment with an official receipt, also on a direct request', function () {
    $project = ($this->makeProject)();

    // Unpaid.
    ($this->submitBid)($project)->assertSessionHasErrors('payment');
    // Pending, rejected, or verified without an OR reference: not paid.
    $payment = ($this->pay)($project, ['status' => BiddingFeePayment::STATUS_PENDING, 'verified_at' => null]);
    ($this->submitBid)($project)->assertSessionHasErrors('payment');
    $payment->update(['status' => BiddingFeePayment::STATUS_REJECTED]);
    ($this->submitBid)($project)->assertSessionHasErrors('payment');
    $payment->update(['status' => BiddingFeePayment::STATUS_VERIFIED, 'verified_at' => now(), 'or_number' => '']);
    ($this->submitBid)($project)->assertSessionHasErrors('payment');
    // A direct JSON request bypassing the page gets the same answer.
    testCase()->actingAs($this->bidder)->postJson(route('bidder.bids.store', $project), [
        'project_id' => $project->id, 'bid_amount' => '2400000',
        'financial_password' => '482913', 'financial_password_confirmation' => '482913',
    ]);
    expect(Bid::count())->toBe(0);
    expect(fn () => app(\App\Support\BidSubmission::class)->assertBiddingFeePaid($project, $this->bidder))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    $payment->update(['or_number' => 'OR-1234567']);
    ($this->submitBid)($project, '2,400,000.50')->assertSessionHasNoErrors();

    // The fee stays apart from the bid price and the bid security.
    $bid = Bid::firstOrFail();
    expect($bid->bid_amount)->toEqual('2400000.50')
        ->and($bid->isDraft())->toBeFalse()
        ->and((float) $payment->fresh()->amount)->toBe(5000.0);
});

it('does not ask for payment when the published fee is waived', function () {
    $project = ($this->makeProject)([], BiddingDocumentsFee::MODE_WAIVED, 'Waived by BAC Resolution No. 2026-025.');
    expect($project->requiresBiddingFee())->toBeFalse();

    testCase()->get(route('public.procurement.show', $project))->assertOk()->assertSee('Waived by the BAC');
    ($this->submitBid)($project)->assertSessionHasNoErrors();
    expect(Bid::firstOrFail()->isDraft())->toBeFalse();
});

it('requires the verified payment before the BAC records a manual sealed bid', function () {
    $project = ($this->makeProject)(['submission_mode' => Project::SUBMISSION_MANUAL]);
    $draft = Bid::create([
        'user_id' => $this->bidder->id, 'project_id' => $project->id, 'bid_amount' => 2400000, 'status' => 'pending',
        'submission_channel' => Bid::CHANNEL_MANUAL, 'workflow_step' => Bid::STEP_SUBMITTED,
    ]);
    $guard = fn () => app(BidWorkflow::class)->guardError($draft->fresh(), BidWorkflow::RECORD_MANUAL_RECEIPT, $this->admin);

    expect($guard())->toContain('bidding documents fee');
    ($this->pay)($project, ['status' => BiddingFeePayment::STATUS_PENDING, 'verified_at' => null]);
    expect($guard())->toContain('bidding documents fee');
    BiddingFeePayment::query()->update(['status' => BiddingFeePayment::STATUS_VERIFIED, 'verified_at' => now()]);
    expect($guard())->toBeNull();

    // A waived fee does not hold up the sealed bid.
    $waived = ($this->makeProject)(['submission_mode' => Project::SUBMISSION_MANUAL], BiddingDocumentsFee::MODE_WAIVED, 'Waived by BAC Resolution No. 2026-026.');
    $other = Bid::create([
        'user_id' => $this->bidder->id, 'project_id' => $waived->id, 'bid_amount' => 2400000, 'status' => 'pending',
        'submission_channel' => Bid::CHANNEL_MANUAL, 'workflow_step' => Bid::STEP_SUBMITTED,
    ]);
    expect(app(BidWorkflow::class)->guardError($other->fresh(), BidWorkflow::RECORD_MANUAL_RECEIPT, $this->admin))->toBeNull();
});
