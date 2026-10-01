<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createConditionalFieldsAdmin(): User
{
    return User::create([
        'name' => 'Conditional Fields Admin',
        'email' => 'conditional-fields-admin@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);
}

it('requires an assigned office for end-user accounts and ignores bidder-only fields', function () {
    $admin = createConditionalFieldsAdmin();
    $office = User::endUserOfficeOptions()[0];

    testCase()
        ->actingAs($admin)
        ->from(route('admin.users', ['create' => 'end_user']))
        ->post(route('admin.users.store'), [
            'name' => 'End User Without Office',
            'email' => 'end-user-without-office@example.com',
            'role' => 'end_user',
            'status' => 'active',
            'password' => 'password',
            'office' => '',
            'company' => 'Should not be stored',
            'registration_no' => 'SHOULD-NOT-BE-STORED',
        ])
        ->assertRedirect(route('admin.users', ['create' => 'end_user']))
        ->assertSessionHasErrors('office');

    testCase()
        ->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'End User Office Account',
            'email' => 'end-user-office@example.com',
            'role' => 'end_user',
            'status' => 'active',
            'password' => 'password',
            'office' => $office,
            'company' => 'Should not be stored',
            'registration_no' => 'SHOULD-NOT-BE-STORED',
        ])
        ->assertRedirect(route('admin.users'));

    testCase()->assertDatabaseHas('users', [
        'email' => 'end-user-office@example.com',
        'role' => 'end_user',
        'office' => $office,
        'company' => null,
        'registration_no' => null,
    ]);
});
