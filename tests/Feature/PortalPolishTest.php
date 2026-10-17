<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::create(['name' => 'Polish Admin', 'email' => 'polish-admin@example.org', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
});

it('names the browser tab of the older admin pages', function () {
    testCase()->actingAs($this->admin)->get(route('admin.users'))->assertOk()->assertSee('<title>Manage users · SJBAC</title>', false);
    testCase()->actingAs($this->admin)->get(route('admin.projects'))->assertOk()->assertSee('<title>Projects &amp; biddings · SJBAC</title>', false);
});

it('shows the year at a glance without the global portal search', function () {
    testCase()->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Open procurements')->assertSee('Fees collected in '.now()->year)
        ->assertDontSee('data-portal-search', false)->assertDontSee('Ctrl K');
});
