<?php

use App\Models\Assignment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::create(['name' => 'Polish Admin', 'email' => 'polish-admin@example.org', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'Polish Staff', 'email' => 'polish-staff@example.org', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'SJBAC']);
    $this->mine = Project::create(['reference_no' => 'SJ-POL-001', 'title' => 'Drainage canal for Poblacion', 'description' => 'x', 'budget' => 500000, 'status' => 'open', 'deadline' => now()->addDays(9)]);
    $this->other = Project::create(['reference_no' => 'SJ-POL-002', 'title' => 'Drainage works for Bubog', 'description' => 'x', 'budget' => 700000, 'status' => 'open', 'deadline' => now()->addDays(9)]);
    Assignment::create(['project_id' => $this->mine->id, 'staff_id' => $this->staff->id]);
    User::create(['name' => 'Drainage Builders Owner', 'email' => 'owner@drainage.example', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Drainage Builders']);
});

it('names the browser tab of the older admin pages', function () {
    testCase()->actingAs($this->admin)->get(route('admin.users'))->assertOk()->assertSee('<title>Manage users · SJBAC</title>', false);
    testCase()->actingAs($this->admin)->get(route('admin.projects'))->assertOk()->assertSee('<title>Projects &amp; biddings · SJBAC</title>', false);
});

it('searches the whole portal for the admin, and only assigned projects (no accounts) for the staff', function () {
    $admin = testCase()->actingAs($this->admin)->getJson(route('portal.search', ['q' => 'drainage']))->assertOk()->json('groups');
    $labels = collect($admin)->pluck('label')->all();
    expect($labels)->toContain('Projects', 'Bidders')
        ->and(collect($admin)->firstWhere('label', 'Projects')['items'])->toHaveCount(2);

    $staff = testCase()->actingAs($this->staff)->getJson(route('portal.search', ['q' => 'drainage']))->assertOk()->json('groups');
    expect(collect($staff)->pluck('label')->all())->not->toContain('Bidders')
        ->and(collect($staff)->firstWhere('label', 'Projects')['items'])->toHaveCount(1);

    $bidder = User::where('role', 'bidder')->first();
    testCase()->actingAs($bidder)->getJson(route('portal.search', ['q' => 'drainage']))->assertForbidden();
});

it('shows the year at a glance and the portal search on the admin dashboard', function () {
    testCase()->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Open procurements')->assertSee('Fees collected in '.now()->year)->assertSee('data-portal-search', false);
});
