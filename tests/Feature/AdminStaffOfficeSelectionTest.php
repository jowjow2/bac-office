<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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
