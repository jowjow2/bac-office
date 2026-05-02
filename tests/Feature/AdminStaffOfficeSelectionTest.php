<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function createAdminStaffOfficeUser(): User
{
    return User::create([
        'name' => 'Admin User',
        'email' => 'admin-staff-office@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);
}

beforeEach(function () {
    testCase()->withoutVite();
});

it('allows admins to create a staff user with an office selection', function () {
    $admin = createAdminStaffOfficeUser();
    $test = testCase();

    $response = $test
        ->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Accounting Staff',
            'email' => 'accounting.staff@example.com',
            'password' => 'password',
            'role' => 'staff',
            'status' => 'active',
            'office' => 'Accounting Office',
            'company' => '',
            'registration_no' => '',
        ]);

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHas('success', 'User created successfully.');

    $test->assertDatabaseHas('users', [
        'email' => 'accounting.staff@example.com',
        'role' => 'staff',
        'office' => 'Accounting Office',
    ]);

    $listingResponse = $test
        ->actingAs($admin)
        ->get(route('admin.users', ['filter' => 'staff']));

    $listingResponse->assertOk();
    $listingResponse->assertSee('Accounting Staff');
    $listingResponse->assertSee('Accounting Office');
});

it('requires an office selection when creating a staff user', function () {
    $admin = createAdminStaffOfficeUser();
    $test = testCase();

    $response = $test
        ->actingAs($admin)
        ->from(route('admin.users'))
        ->post(route('admin.users.store'), [
            'name' => 'Staff Without Office',
            'email' => 'no.office.staff@example.com',
            'password' => 'password',
            'role' => 'staff',
            'status' => 'active',
            'office' => '',
            'company' => '',
            'registration_no' => '',
        ]);

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHasErrors(['office']);

    $test->assertDatabaseMissing('users', [
        'email' => 'no.office.staff@example.com',
    ]);
});

it('allows admins to update the office selection of a staff user', function () {
    $admin = createAdminStaffOfficeUser();
    $test = testCase();

    $staff = User::create([
        'name' => 'Assigned Staff',
        'email' => 'assigned.staff@example.com',
        'password' => Hash::make('password'),
        'role' => 'staff',
        'status' => 'active',
        'office' => 'BAC Office',
    ]);

    $response = $test
        ->actingAs($admin)
        ->put(route('admin.users.update', $staff), [
            'name' => 'Assigned Staff',
            'email' => 'assigned.staff@example.com',
            'password' => '',
            'role' => 'staff',
            'status' => 'active',
            'office' => 'Engineering Office',
            'company' => '',
            'registration_no' => '',
        ]);

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHas('success', 'User updated successfully.');

    $test->assertDatabaseHas('users', [
        'id' => $staff->id,
        'office' => 'Engineering Office',
    ]);
});

it('shows the manage users page even when the bidder approval table is missing', function () {
    $admin = createAdminStaffOfficeUser();
    $test = testCase();

    User::create([
        'name' => 'Legacy Bidder',
        'email' => 'legacy.bidder@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'pending',
        'company' => 'Legacy Builders',
        'registration_no' => 'REG-LEGACY-2',
    ]);

    Schema::dropIfExists('bidders');

    $response = $test
        ->actingAs($admin)
        ->get(route('admin.users'));

    $response->assertOk();
    $response->assertSee('Legacy Bidder');
    $response->assertSee('Review unavailable');
    $response->assertSee('bidder approval table is not present');
});

it('allows admins to create a bidder user even when the bidder approval table is missing', function () {
    $admin = createAdminStaffOfficeUser();
    $test = testCase();

    Schema::dropIfExists('bidders');

    $response = $test
        ->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Legacy Created Bidder',
            'email' => 'legacy.created.bidder@example.com',
            'password' => 'password',
            'role' => 'bidder',
            'status' => 'pending',
            'office' => '',
            'company' => 'Legacy Created Builders',
            'registration_no' => 'REG-LEGACY-3',
        ]);

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHas('success', 'User created successfully.');

    $test->assertDatabaseHas('users', [
        'email' => 'legacy.created.bidder@example.com',
        'role' => 'bidder',
        'company' => 'Legacy Created Builders',
    ]);
});

it('prevents deleting the last remaining admin account', function () {
    $admin = createAdminStaffOfficeUser();
    $test = testCase();

    $response = $test
        ->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin));

    $response->assertRedirect(route('admin.users'));
    $response->assertSessionHasErrors(['delete']);

    $test->assertDatabaseHas('users', [
        'id' => $admin->id,
        'role' => 'admin',
        'email' => 'admin-staff-office@example.com',
    ]);
});
