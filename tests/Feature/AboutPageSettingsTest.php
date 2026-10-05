<?php

use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'about-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
});

it('shows who the BAC is, how to bid, the laws and the FAQ, without an empty composition', function () {
    testCase()->get('/about')->assertOk()
        ->assertSee('Bids and Awards Committee')
        ->assertSee('How to take part in a bidding')
        ->assertSee('&#8369;400,000', false)
        ->assertSee('Laws and official resources')
        ->assertSee('Frequently asked')
        ->assertSee(route('login.page', ['auth_tab' => 'register']), false)
        ->assertDontSee('The Bids and Awards Committee</h2>', false);
});

it('lets the admin set the office details and the BAC composition shown on the About page', function () {
    testCase()->actingAs($this->admin)->get(route('admin.settings.about'))->assertOk()
        ->assertSee('Office details')->assertSee('Technical Working Group');

    testCase()->actingAs($this->admin)->put(route('admin.settings.about.update'), [
        'office' => ['address' => 'BAC Secretariat, 2F Municipal Hall', 'hours' => 'Monday to Friday, 8:00 AM – 5:00 PM', 'email' => 'not-an-email'],
        'members' => [],
    ])->assertSessionHasErrors('office.email');

    testCase()->actingAs($this->admin)->put(route('admin.settings.about.update'), [
        'office' => ['address' => 'BAC Secretariat, 2F Municipal Hall', 'hours' => 'Monday to Friday, 8:00 AM – 5:00 PM', 'email' => 'bac@example.gov.ph'],
        'members' => [
            ['group' => 'bac', 'position' => 'Chairperson', 'name' => 'Juana Dela Cruz'],
            ['group' => 'bac', 'position' => 'Member', 'name' => ''],
            ['group' => 'secretariat', 'position' => 'Head, BAC Secretariat', 'name' => 'Pedro Santos'],
        ],
    ])->assertRedirect(route('admin.settings.about'))->assertSessionHas('success', 'About page updated. 2 people listed.');

    expect(AuditLog::where('action', 'about_page_updated')->count())->toBe(1);
    testCase()->get('/about')->assertOk()
        ->assertSee('Monday to Friday, 8:00 AM – 5:00 PM')
        ->assertSee('bac@example.gov.ph')
        ->assertSeeInOrder(['The Bids and Awards Committee', 'Juana Dela Cruz', 'Chairperson', 'BAC Secretariat', 'Pedro Santos'])
        ->assertDontSee('Technical Working Group');
});

it('keeps the About page and the settings page working before the settings table exists', function () {
    Schema::dropIfExists('site_settings');

    testCase()->get('/about')->assertOk()->assertSee('Bids and Awards Committee');
    testCase()->actingAs($this->admin)->get(route('admin.settings.about'))->assertOk()
        ->assertSee('The database update for these settings is not applied yet');
    testCase()->actingAs($this->admin)->put(route('admin.settings.about.update'), ['office' => [], 'members' => []])->assertStatus(503);
});

it('keeps the settings to the BAC admin', function () {
    $staff = User::create(['name' => 'Staff', 'email' => 'about-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'BAC Secretariat']);
    testCase()->actingAs($staff)->get(route('admin.settings.about'))->assertStatus(403);
    expect(SiteSetting::count())->toBe(0);
});
