<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * End-user office accounts were retired: the BAC admin records an office's
 * purchase requests from its signed hard copy. An account left in the
 * database cannot sign in.
 */
it('refuses to sign in an old end-user office account', function () {
    testCase()->withoutVite();
    User::create([
        'name' => 'Engineering Office', 'email' => 'engineering@example.com', 'password' => Hash::make('secret123'),
        'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office',
    ]);

    testCase()->postJson('/login', ['email' => 'engineering@example.com', 'password' => 'secret123'])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', 'End-user office accounts are no longer used. Hand your purchase request to the BAC as a signed hard copy.');

    testCase()->assertGuest();
});