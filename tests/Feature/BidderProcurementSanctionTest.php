<?php

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BidderSanction;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

function createSanctionAdmin(): User
{
    return User::create([
        'name' => 'Admin User',
        'email' => 'admin-sanctions@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);
}

function createApprovedBidderForSanctions(): User
{
    $user = User::create([
        'name' => 'Bidder User',
        'email' => 'bidder-sanctions@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Sanction Test Company',
        'registration_no' => 'REG-SANCTION-1',
    ]);

    $user->bidderProfile()->create([
        'company_name' => 'Sanction Test Company',
        'contact_person' => 'Bidder User',
        'contact_number' => '09171234567',
        'business_address' => '123 Test Street',
        'approval_status' => 'approved',
        'approved_at' => now(),
    ]);

    return $user;
}

it('requires a reason record before blacklisting a bidder', function () {
    $admin = createSanctionAdmin();
    $bidder = createApprovedBidderForSanctions();

    $response = testCase()
        ->actingAs($admin)
        ->from(route('admin.users.review', $bidder))
        ->patch(route('admin.users.sanction', $bidder), [
            'type' => BidderSanction::TYPE_BLACKLISTED,
            'reference_number' => 'BL-2026-001',
            'effective_date' => now()->toDateString(),
            'authorized_by' => 'BAC Chair',
        ]);

    $response->assertRedirect(route('admin.users.review', $bidder));
    $response->assertSessionHasErrors('reason');

    testCase()->assertDatabaseMissing('bidder_sanctions', [
        'bidder_id' => $bidder->bidderProfile->id,
        'type' => BidderSanction::TYPE_BLACKLISTED,
    ]);

    expect($bidder->fresh()->bidderProcurementStatus())->toBe('active');
});

it('stores sanction details, audits the change, preserves history, and blocks procurement participation', function () {
    $admin = createSanctionAdmin();
    $bidder = createApprovedBidderForSanctions();

    $project = Project::create([
        'title' => 'Road Materials',
        'description' => 'Supply of road materials',
        'budget' => 100000,
        'deadline' => now()->addDays(10),
        'status' => 'open',
        'created_by' => $admin->id,
    ]);

    $historicalBid = Bid::create([
        'project_id' => $project->id,
        'user_id' => $bidder->id,
        'bid_amount' => 95000,
        'status' => 'approved',
        'proposal_file' => 'proposals/historical.pdf',
    ]);

    $response = testCase()
        ->actingAs($admin)
        ->patch(route('admin.users.sanction', $bidder), [
            'type' => BidderSanction::TYPE_SUSPENDED,
            'reason' => 'Submitted falsified eligibility document.',
            'reference_number' => 'SUS-2026-001',
            'effective_date' => now()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'authorized_by' => 'BAC Resolution 2026-09',
        ]);

    $response->assertRedirect(route('admin.users', ['filter' => BidderSanction::TYPE_SUSPENDED]));

    testCase()->assertDatabaseHas('bidder_sanctions', [
        'bidder_id' => $bidder->bidderProfile->id,
        'type' => BidderSanction::TYPE_SUSPENDED,
        'reason' => 'Submitted falsified eligibility document.',
        'reference_number' => 'SUS-2026-001',
        'authorized_by' => 'BAC Resolution 2026-09',
        'created_by' => $admin->id,
    ]);

    testCase()->assertDatabaseHas('audit_logs', [
        'action' => 'bidder_status_changed',
        'auditable_type' => $bidder->bidderProfile::class,
        'auditable_id' => $bidder->bidderProfile->id,
        'user_id' => $admin->id,
    ]);

    expect($bidder->fresh()->isApprovedBidder())->toBeFalse();
    expect($bidder->fresh()->bidderProcurementStatus())->toBe(BidderSanction::TYPE_SUSPENDED);

    testCase()->assertDatabaseHas('users', ['id' => $bidder->id]);
    testCase()->assertDatabaseHas('bidders', ['user_id' => $bidder->id]);
    testCase()->assertDatabaseHas('bids', ['id' => $historicalBid->id]);

    testCase()
        ->actingAs($bidder->fresh())
        ->get(route('bidder.available-projects'))
        ->assertForbidden();
});

it('renders sanction tabs, badges, and sanction details in manage users', function () {
    $admin = createSanctionAdmin();
    $bidder = createApprovedBidderForSanctions();

    $bidder->bidderProfile->sanctions()->create([
        'type' => BidderSanction::TYPE_BLACKLISTED,
        'previous_approval_status' => 'approved',
        'reason' => 'Final blacklisting order after due process.',
        'reference_number' => 'BL-2026-UI',
        'effective_date' => now()->toDateString(),
        'authorized_by' => 'BAC Resolution 2026-10',
        'created_by' => $admin->id,
    ]);

    testCase()
        ->actingAs($admin)
        ->get(route('admin.users', ['filter' => BidderSanction::TYPE_BLACKLISTED]))
        ->assertOk()
        ->assertSee('Suspended')
        ->assertSee('Blacklisted')
        ->assertSee('BL-2026-UI');

    testCase()
        ->actingAs($admin)
        ->get(route('admin.users.review', $bidder))
        ->assertOk()
        ->assertSee('Procurement Sanctions')
        ->assertSee('Final blacklisting order after due process.')
        ->assertSee('BL-2026-UI')
        ->assertSee('BAC Resolution 2026-10');
});
it('does not create a blacklist record when normal account status is changed', function () {
    $admin = createSanctionAdmin();
    $bidder = createApprovedBidderForSanctions();

    $response = testCase()
        ->actingAs($admin)
        ->put(route('admin.users.update', $bidder), [
            'name' => $bidder->name,
            'email' => $bidder->email,
            'username' => '',
            'role' => 'bidder',
            'status' => 'rejected',
            'company' => $bidder->company,
            'registration_no' => $bidder->registration_no,
            'password' => '',
        ]);

    $response->assertRedirect(route('admin.users'));

    expect(BidderSanction::count())->toBe(0);
    expect($bidder->fresh()->bidderProcurementStatus())->toBe('rejected');
});