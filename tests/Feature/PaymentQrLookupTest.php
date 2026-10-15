<?php

use App\Models\Bidder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'qr-pay-admin@example.org', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'BAC Staff', 'email' => 'qr-pay-staff@example.org', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);
    $this->makeBidder = function (string $status, string $approval) {
        $user = User::create(['name' => 'Rosa Reyes', 'email' => "rosa-$status@example.org", 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => $status, 'company' => 'Reyes Builders']);
        $profile = Bidder::create(['user_id' => $user->id, 'company_name' => 'Reyes Builders', 'contact_person' => 'Rosa Reyes', 'contact_number' => '09170001111', 'business_address' => 'San Jose', 'approval_status' => $approval]);

        return [$user, $profile->fresh()];
    };
});

it('finds the approved bidder behind a scanned QR code, for the admin and the staff', function () {
    [$user, $profile] = ($this->makeBidder)('active', 'approved');

    $page = testCase()->actingAs($this->admin)->get(route('admin.payments'))->assertOk();
    $page->assertSee('data-fee-open-scan', false)->assertSee('id="fee-scan-dialog"', false)->assertSee('bidder-lookup', false);

    testCase()->actingAs($this->admin)->getJson(route('admin.payments.bidder-lookup', ['token' => $profile->qr_token]))
        ->assertOk()->assertJson(['ok' => true, 'id' => $user->id, 'label' => 'Reyes Builders']);
    testCase()->actingAs($this->staff)->getJson(route('staff.payments.bidder-lookup', ['token' => $profile->qr_token]))
        ->assertOk()->assertJson(['ok' => true, 'id' => $user->id]);
});

it('refuses an unknown code and a bidder who is not approved', function () {
    [, $pending] = ($this->makeBidder)('pending', 'pending');

    testCase()->actingAs($this->admin)->getJson(route('admin.payments.bidder-lookup', ['token' => 'not-a-real-token-123']))
        ->assertNotFound()->assertJson(['ok' => false]);
    testCase()->actingAs($this->admin)->getJson(route('admin.payments.bidder-lookup', ['token' => $pending->qr_token]))
        ->assertStatus(422)->assertJson(['ok' => false]);
});

it('keeps the lookup to signed-in BAC users', function () {
    [, $profile] = ($this->makeBidder)('active', 'approved');
    $bidder = User::where('email', 'rosa-active@example.org')->first();

    testCase()->getJson(route('admin.payments.bidder-lookup', ['token' => $profile->qr_token]))->assertUnauthorized();
    testCase()->actingAs($bidder)->getJson(route('admin.payments.bidder-lookup', ['token' => $profile->qr_token]))->assertForbidden();
});
