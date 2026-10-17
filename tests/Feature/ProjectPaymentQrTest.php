<?php

use App\Models\BiddingFeePayment;
use App\Models\Project;
use App\Models\User;
use App\Support\BiddingFeeQr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $this->staff = User::factory()->create(['role' => 'staff', 'status' => 'active']);
    $this->bidder = User::factory()->create(['name' => 'Cedrick Portacio', 'company' => 'Portacio Construction', 'role' => 'bidder', 'status' => 'active']);
    $this->other = User::factory()->create(['role' => 'bidder', 'status' => 'active']);
    $this->project = Project::create([
        'title' => 'Supply and Delivery of Fuel', 'description' => 'Fuel for municipal vehicles',
        'reference_no' => 'SJ-BAC-2026-G-D10', 'category' => 'goods', 'procurement_mode' => 'public_bidding',
        'budget' => 1950000, 'deadline' => now()->addDays(5), 'status' => 'open', 'bidding_documents_fee' => 5000,
    ]);
    $this->token = BiddingFeeQr::encode($this->bidder, $this->project);
});

it('provides private QR images only for approved bidders and payable open projects', function () {
    testCase()->getJson(route('bidder.payment-qr', $this->project))->assertUnauthorized();
    testCase()->actingAs($this->admin)->getJson(route('bidder.payment-qr', $this->project))->assertForbidden();
    testCase()->actingAs($this->bidder)->get(route('bidder.payment-qr', $this->project))
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertOk()->assertSee('Download Payment QR')->assertSee(route('bidder.payment-qr', $this->project), false);
    $this->project->update(['status' => 'closed']);
    testCase()->actingAs($this->bidder)->getJson(route('bidder.payment-qr', $this->project))->assertNotFound();
});

it('binds the reference to the bidder and project and looks up current details without recording a payment', function () {
    expect(BiddingFeeQr::decode($this->token))->toBe(['bidder' => $this->bidder->id, 'project' => $this->project->id])
        ->and(BiddingFeeQr::decode(BiddingFeeQr::encode($this->other, $this->project))['bidder'])->toBe($this->other->id);
    $this->project->update(['bidding_documents_fee' => 6000]);
    foreach ([$this->admin, $this->staff] as $operator) {
        testCase()->actingAs($operator)->getJson(route($operator->role.'.payments.bidder-lookup', ['token' => $this->token]))
            ->assertOk()->assertJsonPath('id', $this->bidder->id)->assertJsonPath('name', 'Cedrick Portacio')
            ->assertJsonPath('project.id', $this->project->id)->assertJsonPath('project.reference', 'SJ-BAC-2026-G-D10')
            ->assertJsonPath('project.amount', '6000.00');
    }
    expect(BiddingFeePayment::count())->toBe(0)->and($this->project->fresh()->hasPaidBiddingFee($this->bidder))->toBeFalse();
});

it('rejects damaged references and references meant for another purpose', function () {
    $tampered = $this->token;
    $tampered[20] = $tampered[20] === 'A' ? 'B' : 'A';
    foreach ([$tampered, BiddingFeeQr::PREFIX.'invalid', BiddingFeeQr::PREFIX.Crypt::encryptString('{"purpose":"other","bidder":1,"project":1}')] as $token) {
        testCase()->actingAs($this->admin)->getJson(route('admin.payments.bidder-lookup', ['token' => $token]))->assertNotFound();
    }
    testCase()->actingAs($this->bidder)->getJson(route('admin.payments.bidder-lookup', ['token' => $this->token]))->assertForbidden();
});

it('rechecks approval, closing dates, waived fees and existing receipts when a saved QR is scanned', function () {
    $this->bidder->update(['status' => 'pending']);
    testCase()->actingAs($this->admin)->getJson(route('admin.payments.bidder-lookup', ['token' => $this->token]))->assertStatus(422);
    $this->bidder->update(['status' => 'active']);
    $this->project->update(['deadline' => now()->subMinute()]);
    testCase()->getJson(route('admin.payments.bidder-lookup', ['token' => $this->token]))->assertStatus(422);
    $this->project->update(['deadline' => now()->addDays(5), 'bidding_documents_fee' => 0]);
    testCase()->getJson(route('admin.payments.bidder-lookup', ['token' => $this->token]))->assertStatus(422);
    $this->project->update(['bidding_documents_fee' => 5000]);
    BiddingFeePayment::create(['project_id' => $this->project->id, 'user_id' => $this->bidder->id,
        'amount' => 5000, 'or_number' => 'QR-EXISTING', 'status' => 'verified', 'verified_at' => now(), 'paid_at' => now(), 'recorded_by' => $this->admin->id]);
    testCase()->getJson(route('admin.payments.bidder-lookup', ['token' => $this->token]))->assertStatus(422);
    testCase()->actingAs($this->bidder)->getJson(route('bidder.payment-qr', $this->project))->assertStatus(409);
});

it('requires the scanned pair to match the recorded payment and unlocks only that project after recording an OR', function () {
    $data = ['_form' => 'record', 'project_id' => $this->project->id, 'user_id' => $this->other->id,
        'amount' => 5000, 'or_number' => 'QR-OR-1001', 'paid_at' => now()->toDateString(), 'payment_qr' => $this->token];
    testCase()->actingAs($this->staff)->post(route('staff.payments.store'), $data)->assertSessionHasErrors('payment_qr');
    expect(BiddingFeePayment::count())->toBe(0);
    $data['user_id'] = $this->bidder->id;
    testCase()->post(route('staff.payments.store'), $data)->assertSessionHasNoErrors();
    expect($this->project->fresh()->hasPaidBiddingFee($this->bidder))->toBeTrue()
        ->and($this->project->fresh()->hasPaidBiddingFee($this->other))->toBeFalse();
    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))->assertOk()->assertDontSee('Download Payment QR')->assertSee('QR-OR-1001');
});
