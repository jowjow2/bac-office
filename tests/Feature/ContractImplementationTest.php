<?php

use App\Models\Assignment;
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

    $makeUser = fn ($email, $role) => User::create([
        'name' => ucfirst($role).' Test',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => $role,
        'status' => 'active',
        'company' => $role === 'bidder' ? 'Winning Supplier Ltd.' : null,
    ]);
    $this->admin = $makeUser('ci-admin@example.com', 'admin');
    $this->staff = $makeUser('ci-staff@example.com', 'staff');
    $this->unassignedStaff = $makeUser('ci-unassigned@example.com', 'staff');
    $this->supplier = $makeUser('ci-supplier@example.com', 'bidder');
    $this->otherBidder = $makeUser('ci-other@example.com', 'bidder');

    $this->pdf = fn ($name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

    $this->makeAward = function (array $overrides = []): Award {
        $project = Project::create(array_merge([
            'title' => 'Supply and delivery of office equipment',
            'description' => 'Supply contract test project.',
            'reference_no' => 'SJOM-CI-'.strtoupper(substr(md5((string) random_int(1, 999999)), 0, 6)),
            'category' => 'goods',
            'procurement_mode' => 'public_bidding',
            'legal_basis' => 'ra_12009',
            'budget' => 500000,
            'contract_duration' => '30 calendar days from receipt of NTP',
            'location' => 'Municipality of San Jose, Occidental Mindoro',
            'status' => 'awarded',
            'deadline' => now()->subDay(),
        ], $overrides['project'] ?? []));

        $bid = Bid::create([
            'project_id' => $project->id,
            'user_id' => $overrides['supplier_id'] ?? $this->supplier->id,
            'bid_amount' => 495000,
            'status' => 'awarded',
            'workflow_step' => Bid::STEP_NOTICE_TO_PROCEED,
            'submission_channel' => Bid::CHANNEL_ELECTRONIC,
            'submitted_at' => now()->subDays(20),
            'contract_signed_at' => now()->subDays(5),
            'contract_signed_by' => $this->admin->id,
            'notice_of_award_at' => now()->subDays(7),
            'notice_to_proceed_at' => now()->subDays(3),
            'notice_to_proceed_by' => $this->admin->id,
        ]);

        Assignment::create(['staff_id' => $this->staff->id, 'project_id' => $project->id, 'role_in_project' => 'BAC Secretariat']);

        return Award::create([
            'project_id' => $project->id,
            'bid_id' => $bid->id,
            'bidder_id' => $bid->user_id,
            'contract_amount' => 495000,
            'contract_date' => now()->subDays(5)->toDateString(),
            'notice_of_award_date' => now()->subDays(7)->toDateString(),
            'status' => 'active',
        ])->load(['project', 'bid.user']);
    };

    $this->terms = [
        'delivery_deadline' => now()->addDays(25)->toDateString(),
        'delivery_location' => 'Municipal Warehouse, San Jose',
        'signed_contract_reference' => 'Contract No. SJOM-2026-001, Schedule A',
        'contract_items' => [
            ['description' => 'Desktop computer', 'quantity' => 2, 'unit' => 'units', 'unit_price' => 45000],
            ['description' => 'Laser printer', 'quantity' => 1, 'unit' => 'unit', 'unit_price' => 15000],
        ],
        'supply_type' => 'non_expendable',
        'warranty_months' => 12,
        'warranty_security' => 'retention',
        'warranty_percent' => 1,
        'remarks' => 'Delivery terms copied from the signed contract.',
    ];

    $this->configure = fn (Award $award, ?User $user = null) => testCase()->actingAs($user ?? $this->admin)
        ->put(route('admin.contract-implementation.configure', $award), $this->terms + ['document' => ($this->pdf)('signed-contract.pdf')]);

    $this->delivery = fn (Award $award, array $extra = []) => testCase()->actingAs($this->supplier)
        ->post(route('bidder.contract-implementation.delivery', $award), array_merge([
            'delivered_on' => now()->subDay()->toDateString(),
            'delivery_reference' => 'DR-2026-001',
            'delivered_quantities' => [2, 1],
            'remarks' => 'Delivered to the municipal warehouse.',
            'document' => ($this->pdf)('delivery-receipt.pdf'),
        ], $extra));

    $this->lguAction = fn (Award $award, string $action, array $extra = [], ?User $user = null) => testCase()->actingAs($user ?? $this->staff)
        ->post(route(($user?->role === 'admin' ? 'admin' : 'staff').'.contract-implementation.action', $award), array_merge([
            'action' => $action,
            'remarks' => ucfirst(str_replace('_', ' ', $action)).' recorded.',
            'document' => ($this->pdf)($action.'.pdf'),
        ], $extra));
});

it('runs the Goods implementation from signed terms through completion with private, attributable history', function () {
    $award = ($this->makeAward)();

    // Each award row opens its own Contract Implementation modal.
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()->assertSee('Contract Implementation')
        ->assertSee('data-ci-open="'.$award->id.'"', false)
        ->assertSee('id="ci-dialog-'.$award->id.'"', false);
    testCase()->actingAs($this->supplier)->get(route('bidder.awarded-contracts'))->assertOk()->assertSee('Contract Implementation')
        ->assertSee('data-ci-open="'.$award->id.'"', false);

    // Saving returns to the same contract, and the page reopens its modal.
    ($this->configure)($award)->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.awards.index', ['contract' => $award->id]));
    testCase()->actingAs($this->admin)->get(route('admin.awards.index', ['contract' => $award->id]))
        ->assertSee('data-ci-reopen', false);
    $implementation = ContractImplementation::where('award_id', $award->id)->firstOrFail();
    expect($implementation->status)->toBe(ContractImplementation::FOR_DELIVERY)
        ->and($implementation->delivery_deadline->toDateString())->toBe($this->terms['delivery_deadline'])
        ->and($implementation->delivery_deadline->toDateString())->not->toBe($award->bid->notice_to_proceed_at->toDateString())
        ->and($implementation->contract_items)->toHaveCount(2);

    ($this->delivery)($award)->assertSessionHasNoErrors();
    expect($implementation->fresh()->status)->toBe(ContractImplementation::DELIVERED);

    $deliveryEvent = $implementation->events()->where('action', 'supplier_delivery')->firstOrFail();
    testCase()->actingAs($this->otherBidder)->get(route('contract-implementation.document', $deliveryEvent))->assertForbidden();
    testCase()->actingAs($this->unassignedStaff)->get(route('contract-implementation.document', $deliveryEvent))->assertForbidden();
    testCase()->actingAs($this->supplier)->get(route('contract-implementation.document', $deliveryEvent))->assertOk();
    testCase()->actingAs($this->staff)->get(route('contract-implementation.document', $deliveryEvent))->assertOk();

    ($this->lguAction)($award, 'receive', [
        'actual_received_on' => now()->toDateString(),
        'received_quantities' => [2, 1],
    ])->assertSessionHasNoErrors();
    expect($implementation->fresh()->status)->toBe(ContractImplementation::FOR_INSPECTION);

    ($this->lguAction)($award, 'inspect', [
        'outcome' => 'accepted',
        'inspected_on' => now()->toDateString(),
        'iar_number' => 'IAR 2026-001',
        'inspection_findings' => 'All items match the signed specifications.',
        'accepted_quantities' => [2, 1],
    ])->assertSessionHasNoErrors();
    expect($implementation->fresh()->status)->toBe(ContractImplementation::ACCEPTED)
        ->and($implementation->fresh()->accepted_on->toDateString())->toBe(now()->toDateString());

    // Delivered on time: no liquidated damages; 1% retention on ₱495,000 is withheld for the warranty.
    ($this->lguAction)($award, 'payment_processing', [], $this->admin)->assertSessionHasNoErrors();
    $payment = $implementation->events()->where('action', 'payment_processing')->firstOrFail();
    expect($payment->details['liquidated_damages'])->toEqual(0)
        ->and($payment->details['warranty_security_amount'])->toEqual(4950);
    ($this->lguAction)($award, 'paid', [], $this->admin)->assertSessionHasNoErrors();

    // Non-expendable supplies: the contract completes only after the 1-year warranty (IRR Sec. 90.1).
    $close = ['warranty_released_on' => now()->toDateString(), 'no_defects' => '1'];
    ($this->lguAction)($award, 'complete', $close, $this->admin)->assertSessionHasErrors('warranty');
    $this->travel(12)->months();
    ($this->lguAction)($award, 'complete', ['no_defects' => '1', 'warranty_released_on' => now()->toDateString()], $this->admin)->assertSessionHasNoErrors();

    $implementation->refresh()->load('events.actor');
    expect($implementation->status)->toBe(ContractImplementation::COMPLETED)
        ->and($implementation->events)->toHaveCount(7)
        ->and($implementation->events->every(fn ($event) => $event->actor_id !== null && $event->occurred_at !== null && filled($event->remarks) && filled($event->document_path)))->toBeTrue()
        ->and(Storage::disk('local')->exists($deliveryEvent->document_path))->toBeTrue();

    expect(App\Models\UserNotification::where('user_id', $this->supplier->id)->where('type', 'system_alert')->count())->toBe(6)
        ->and(App\Models\UserNotification::where('user_id', $this->supplier->id)->where('message', 'like', '%accepted%')->exists())->toBeTrue();

    ($this->lguAction)($award, 'payment_processing', [], $this->admin)->assertSessionHasErrors('status');
});

it('returns a deficient delivery through correction and permits a corrected delivery cycle', function () {
    $award = ($this->makeAward)();
    ($this->configure)($award)->assertSessionHasNoErrors();
    ($this->delivery)($award)->assertSessionHasNoErrors();

    ($this->lguAction)($award, 'receive', [
        'actual_received_on' => now()->toDateString(),
        'received_quantities' => [2, 1],
    ])->assertSessionHasNoErrors();
    ($this->lguAction)($award, 'inspect', [
        'outcome' => 'for_correction',
        'inspected_on' => now()->toDateString(),
        'iar_number' => 'IAR 2026-001',
        'inspection_findings' => 'One computer is damaged.',
        'accepted_quantities' => [1, 1],
        'deficiencies' => 'One desktop computer arrived damaged.',
        'correction_request' => 'Replace the damaged desktop computer.',
    ])->assertSessionHasNoErrors();

    expect(ContractImplementation::where('award_id', $award->id)->value('status'))->toBe(ContractImplementation::FOR_CORRECTION);
    testCase()->actingAs($this->supplier)->get(route('bidder.awarded-contracts'))->assertOk()->assertSee('Replace the damaged desktop computer.');

    ($this->delivery)($award, [
        'delivered_on' => now()->toDateString(),
        'delivery_reference' => 'DR-2026-002',
        'delivered_quantities' => [1, 0],
        'remarks' => 'Replacement desktop delivered.',
        'document' => ($this->pdf)('replacement-receipt.pdf'),
    ])->assertSessionHasNoErrors();
    ($this->lguAction)($award, 'receive', [
        'actual_received_on' => now()->toDateString(),
        'received_quantities' => [1, 0],
    ])->assertSessionHasNoErrors();
    ($this->lguAction)($award, 'inspect', [
        'outcome' => 'accepted',
        'inspected_on' => now()->toDateString(),
        'iar_number' => 'IAR 2026-001',
        'inspection_findings' => 'Replacement unit inspected and accepted; previous accepted printer/computer remain covered by prior inspection.',
        'accepted_quantities' => [2, 1],
    ])->assertSessionHasNoErrors();

    expect(ContractImplementation::where('award_id', $award->id)->value('status'))->toBe(ContractImplementation::ACCEPTED)
        ->and(ContractImplementationEvent::where('contract_implementation_id', ContractImplementation::where('award_id', $award->id)->value('id'))->count())->toBe(7);
});

it('blocks wrong users, unassigned staff, non-Goods, invalid stages, and duplicate deliveries', function () {
    $award = ($this->makeAward)();
    $otherAward = ($this->makeAward)(['supplier_id' => $this->otherBidder->id]);

    testCase()->actingAs($this->otherBidder)
        ->post(route('bidder.contract-implementation.delivery', $award), [])->assertForbidden();
    testCase()->actingAs($this->unassignedStaff)
        ->post(route('staff.contract-implementation.action', $award), ['action' => 'payment_processing'])->assertForbidden();

    ($this->configure)($award)->assertSessionHasNoErrors();
    ($this->lguAction)($award, 'payment_processing')->assertSessionHasErrors('status');
    ($this->delivery)($award)->assertSessionHasNoErrors();
    ($this->delivery)($award)->assertSessionHasErrors('status');

    $infraAward = ($this->makeAward)(['project' => ['category' => 'infrastructure']]);
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk();
    expect(ContractImplementation::where('award_id', $infraAward->id)->exists())->toBeFalse();
    testCase()->actingAs($this->supplier)->get(route('bidder.awarded-contracts'))->assertOk();
    expect(ContractImplementation::where('award_id', $infraAward->id)->exists())->toBeFalse();

    expect($otherAward->bid->user_id)->toBe($this->otherBidder->id);
});

it('creates a safe For Delivery tracker for a legacy Goods award that already has an NTP', function () {
    $award = ($this->makeAward)();

    expect(ContractImplementation::where('award_id', $award->id)->exists())->toBeFalse();
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()->assertSee('Awaiting signed contract details');

    $implementation = ContractImplementation::where('award_id', $award->id)->firstOrFail();
    expect($implementation->status)->toBe(ContractImplementation::FOR_DELIVERY)
        ->and($implementation->delivery_deadline)->toBeNull()
        ->and($implementation->contract_items)->toBeNull();

    testCase()->actingAs($this->supplier)->get(route('bidder.awarded-contracts'))->assertOk()
        ->assertSee('Contract Implementation')
        ->assertDontSee('Submit delivery for inspection');
});

it('charges liquidated damages of 0.1% of the delayed goods per day until delivered and accepted', function () {
    $award = ($this->makeAward)();
    // NTP was 3 days ago; the signed contract's deadline passed 2 days ago.
    $this->terms['delivery_deadline'] = now()->subDays(2)->toDateString();
    ($this->configure)($award)->assertSessionHasNoErrors();
    $implementation = ContractImplementation::where('award_id', $award->id)->firstOrFail();

    // Nothing delivered yet: ₱105,000 of goods × 0.1% × 2 days, still accruing.
    $accruing = App\Support\LiquidatedDamages::for($implementation->fresh('events'), 495000);
    expect($accruing['total'])->toEqual(210.0)
        ->and($accruing['accruing'])->toBeTrue()
        ->and($accruing['max_days'])->toBe(2);

    ($this->delivery)($award, ['delivered_on' => now()->toDateString()])->assertSessionHasNoErrors();
    ($this->lguAction)($award, 'receive', ['actual_received_on' => now()->toDateString(), 'received_quantities' => [2, 1]])->assertSessionHasNoErrors();
    // Inspected three days later: the LGU's inspection time is not charged to the supplier.
    $this->travel(3)->days();
    ($this->lguAction)($award, 'inspect', [
        'outcome' => 'accepted', 'inspected_on' => now()->toDateString(), 'iar_number' => 'IAR 2026-002',
        'inspection_findings' => 'Complete and conforming.', 'accepted_quantities' => [2, 1],
    ])->assertSessionHasNoErrors();

    $final = App\Support\LiquidatedDamages::for($implementation->fresh('events'), 495000);
    expect($final['total'])->toEqual(210.0)
        ->and($final['accruing'])->toBeFalse()
        ->and($final['may_rescind'])->toBeFalse();

    ($this->lguAction)($award, 'payment_processing', [], $this->admin)->assertSessionHasNoErrors();
    expect($implementation->events()->where('action', 'payment_processing')->firstOrFail()->details['liquidated_damages'])->toEqual(210);
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()->assertSee('Liquidated damages');
});

it('records a HoPE-approved extension requested before the deadline and within the initial delivery period', function () {
    $award = ($this->makeAward)();
    // NTP 3 days ago, deadline in 10 days: the initial delivery period is 13 days.
    $this->terms['delivery_deadline'] = now()->addDays(10)->toDateString();
    ($this->configure)($award)->assertSessionHasNoErrors();
    $extend = fn (array $data) => ($this->lguAction)($award, 'extend', array_merge([
        'requested_on' => now()->toDateString(),
        'approved_on' => now()->toDateString(),
        'new_deadline' => now()->addDays(20)->toDateString(),
        'approval_reference' => 'Memorandum No. 2026-114',
    ], $data));

    $extend(['requested_on' => now()->addDays(11)->toDateString()])->assertSessionHasErrors('requested_on');
    $extend(['new_deadline' => now()->addDays(24)->toDateString()])->assertSessionHasErrors('new_deadline');
    $extend(['new_deadline' => now()->addDays(5)->toDateString()])->assertSessionHasErrors('new_deadline');
    $extend([])->assertSessionHasNoErrors();

    $implementation = ContractImplementation::where('award_id', $award->id)->firstOrFail();
    expect($implementation->revised_deadline->toDateString())->toBe(now()->addDays(20)->toDateString())
        ->and($implementation->delivery_deadline->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and($implementation->damagesDeadline()->toDateString())->toBe(now()->addDays(20)->toDateString())
        ->and($implementation->events()->where('action', 'deadline_extended')->exists())->toBeTrue();

    // An extension that keeps liquidated damages counts them from the original deadline.
    $extend(['new_deadline' => now()->addDays(23)->toDateString(), 'keeps_liquidated_damages' => '1'])->assertSessionHasNoErrors();
    expect($implementation->fresh()->damagesDeadline()->toDateString())->toBe(now()->addDays(10)->toDateString());
});

it('takes the warranty terms at close-out for older contracts and releases expendables once consumed', function () {
    $award = ($this->makeAward)();
    ($this->configure)($award)->assertSessionHasNoErrors();
    $implementation = ContractImplementation::where('award_id', $award->id)->firstOrFail();
    // Recorded before warranty terms were captured.
    $implementation->update(['supply_type' => null, 'warranty_months' => null, 'warranty_security' => null, 'warranty_percent' => null]);

    ($this->delivery)($award)->assertSessionHasNoErrors();
    ($this->lguAction)($award, 'receive', ['actual_received_on' => now()->toDateString(), 'received_quantities' => [2, 1]])->assertSessionHasNoErrors();
    ($this->lguAction)($award, 'inspect', ['outcome' => 'accepted', 'inspected_on' => now()->toDateString(), 'iar_number' => 'IAR 2026-003', 'inspection_findings' => 'Conforming.', 'accepted_quantities' => [2, 1]])->assertSessionHasNoErrors();
    ($this->lguAction)($award, 'payment_processing', [], $this->admin)->assertSessionHasNoErrors();
    ($this->lguAction)($award, 'paid', [], $this->admin)->assertSessionHasNoErrors();

    $close = ['warranty_released_on' => now()->toDateString(), 'no_defects' => '1', 'supply_type' => 'expendable', 'warranty_months' => 3, 'warranty_security' => 'retention'];
    ($this->lguAction)($award, 'complete', ['warranty_months' => 2] + $close, $this->admin)->assertSessionHasErrors('warranty_months');
    ($this->lguAction)($award, 'complete', $close, $this->admin)->assertSessionHasErrors('warranty');
    ($this->lguAction)($award, 'complete', $close + ['consumed' => '1'], $this->admin)->assertSessionHasNoErrors();

    $implementation->refresh();
    expect($implementation->status)->toBe(ContractImplementation::COMPLETED)
        ->and($implementation->supply_type)->toBe('expendable')
        ->and((float) $implementation->warranty_percent)->toBe(1.0)
        ->and($implementation->warranty_released_on->toDateString())->toBe(now()->toDateString());
});

it('gives the supplier a focused delivery modal that reopens with its errors', function () {
    $award = ($this->makeAward)();

    // No delivery modal until the LGU records the contract terms.
    testCase()->actingAs($this->supplier)->get(route('bidder.awarded-contracts'))->assertOk()
        ->assertDontSee('id="ci-delivery-'.$award->id.'"', false);

    ($this->configure)($award)->assertSessionHasNoErrors();
    testCase()->actingAs($this->supplier)->get(route('bidder.awarded-contracts'))->assertOk()
        ->assertSee('id="ci-delivery-'.$award->id.'"', false)
        ->assertSee('data-ci-delivery="'.$award->id.'"', false)
        ->assertSee('Deliver to')
        ->assertSee('Submit delivery for inspection');

    // A submission without the receipt comes back to the delivery modal, not the whole contract screen.
    testCase()->actingAs($this->supplier)->from(route('bidder.awarded-contracts'))
        ->post(route('bidder.contract-implementation.delivery', $award), ['ci_award' => $award->id, 'ci_form' => 'delivery', 'delivered_on' => now()->toDateString()])
        ->assertSessionHasErrors(['document', 'delivery_reference']);
    testCase()->actingAs($this->supplier)->get(route('bidder.awarded-contracts'))
        ->assertSee('data-ci-reopen-delivery', false)
        ->assertDontSee('data-ci-reopen ', false)
        ->assertSee('The delivery was not submitted.');
});

it('shows the LGU one next step at a time, each opening its own focused modal', function () {
    $award = ($this->makeAward)();
    $admin = fn () => testCase()->actingAs($this->admin)->get(route('admin.awards.index'));

    $admin()->assertOk()
        ->assertSee('Record the contract terms')
        ->assertSee('data-ci-step="ci-'.$award->id.'-step-terms"', false)
        ->assertSee('id="ci-'.$award->id.'-step-terms"', false);
    $reopened = fn ($response) => (bool) preg_match('/<dialog[^>]*data-ci-reopen-step/', $response->getContent());
    expect($reopened($admin()))->toBeFalse();

    // A failed save reopens that step's modal with its errors.
    testCase()->actingAs($this->admin)->from(route('admin.awards.index'))
        ->put(route('admin.contract-implementation.configure', $award), ['ci_award' => $award->id, 'ci_form' => 'terms'])
        ->assertSessionHasErrors('delivery_deadline');
    $failed = $admin()->assertSee('This step was not saved.');
    expect($reopened($failed))->toBeTrue();

    ($this->configure)($award)->assertSessionHasNoErrors();
    $admin()->assertSee('Waiting for the supplier to deliver')
        ->assertSee('data-ci-step="ci-'.$award->id.'-step-extension"', false)
        ->assertDontSee('id="ci-'.$award->id.'-step-terms"', false);

    ($this->delivery)($award)->assertSessionHasNoErrors();
    $admin()->assertSee('Record the actual receipt')
        ->assertSee('DR-2026-001')
        ->assertSee('id="ci-'.$award->id.'-step-receive"', false);
});
