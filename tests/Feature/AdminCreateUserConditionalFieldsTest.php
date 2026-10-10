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

it('no longer creates end-user office accounts', function () {
    $admin = createConditionalFieldsAdmin();

    testCase()->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'End User Office Account', 'email' => 'end-user-office@example.com', 'role' => 'end_user',
        'status' => 'active', 'password' => 'password', 'office' => User::endUserOfficeOptions()[0],
    ])->assertSessionHasErrors('role');

    testCase()->assertDatabaseMissing('users', ['email' => 'end-user-office@example.com']);
});
