<?php

use App\Models\Bidder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Admin → Suppliers & users → Create user: the fields each account type needs
 * (office, position and contact for offices; company details for bidders).
 */
beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'form-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
});

it('creates a staff account with its position and office contact number', function () {
    $office = User::staffOfficeOptions()[0];

    testCase()->actingAs($this->admin)->post(route('admin.users.store'), [
        'role' => 'staff',
        'office' => $office,
        'position' => 'Municipal Engineer',
        'contact_number' => '(043) 457-1234',
        'name' => 'Engr. Mark Anthony Reyes',
        'email' => 'mark.reyes@example.com',
        'password' => 'secret123',
        'status' => 'active',
    ])->assertRedirect(route('admin.users'))->assertSessionHasNoErrors();

    $user = User::where('email', 'mark.reyes@example.com')->firstOrFail();
    expect($user->role)->toBe('staff')
        ->and($user->office)->toBe($office)
        ->and($user->position)->toBe('Municipal Engineer')
        ->and($user->contact_number)->toBe('(043) 457-1234');

    testCase()->actingAs($this->admin)->get(route('admin.users'))->assertOk()
        ->assertSee('Municipal Engineer')
        ->assertSee('data-position="Municipal Engineer"', false);
});

it('creates a bidder with company details on its bidder profile', function () {
    testCase()->actingAs($this->admin)->post(route('admin.users.store'), [
        'role' => 'bidder',
        'company' => 'Juan Dela Cruz Construction',
        'registration_no' => 'DTI-2026-0099',
        'contact_number' => '0917 123 4567',
        'business_address' => 'Brgy. Poblacion, San Jose, Occidental Mindoro',
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'password' => 'secret123',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'juan@example.com')->firstOrFail();
    $profile = Bidder::where('user_id', $user->id)->firstOrFail();

    expect($user->company)->toBe('Juan Dela Cruz Construction')
        ->and($user->position)->toBeNull()
        ->and($profile->contact_person)->toBe('Juan Dela Cruz')
        ->and($profile->contact_number)->toBe('0917 123 4567')
        ->and($profile->business_address)->toBe('Brgy. Poblacion, San Jose, Occidental Mindoro');
});

it('asks for the company name of a bidder and checks the contact number', function () {
    testCase()->actingAs($this->admin)->post(route('admin.users.store'), [
        'role' => 'bidder',
        'company' => '',
        'contact_number' => 'call me maybe',
        'name' => 'No Company',
        'email' => 'nocompany@example.com',
        'password' => 'secret123',
        'status' => 'active',
    ])->assertSessionHasErrors(['company', 'contact_number']);

    expect(User::where('email', 'nocompany@example.com')->exists())->toBeFalse();
});

it('updates a staff member\'s position and keeps it off bidders', function () {
    $staff = User::create(['name' => 'Jose Reyes', 'email' => 'jose@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => User::staffOfficeOptions()[0]]);

    testCase()->actingAs($this->admin)->put(route('admin.users.update', $staff), [
        'role' => 'staff',
        'office' => $staff->office,
        'position' => 'BAC Secretariat Head',
        'contact_number' => '(043) 491-0000',
        'name' => 'Jose Reyes',
        'email' => 'jose@example.com',
        'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($staff->fresh()->position)->toBe('BAC Secretariat Head')
        ->and($staff->fresh()->contact_number)->toBe('(043) 491-0000');
});

it('exports the users of the current tab and search as a spreadsheet', function () {
    $admin = User::create(['name' => 'Export Admin', 'email' => 'export-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $staff = User::create(['name' => 'Export Staff', 'email' => 'export-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'Supply Office']);
    $bidder = User::create(['name' => 'Maria Cruz', 'email' => 'export-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Cruz Builders', 'registration_no' => 'DTI-1234']);

    $page = testCase()->actingAs($admin)->get(route('admin.users'))->assertOk();
    $page->assertSee('data-export-title="Export users"', false)->assertSee(route('admin.users.export'), false);

    $all = testCase()->actingAs($admin)->get(route('admin.users.export'))->assertOk()->streamedContent();
    expect($all)->toContain('Name,Email,Role,Company')->toContain('export-admin@example.com')->toContain('Cruz Builders')->toContain('Supply Office')
        ->not->toContain('password');

    $bidders = testCase()->actingAs($admin)->get(route('admin.users.export', ['filter' => 'bidder']))->assertOk()->streamedContent();
    expect($bidders)->toContain('export-bidder@example.com')->not->toContain('export-staff@example.com');

    $found = testCase()->actingAs($admin)->get(route('admin.users.export', ['search' => 'Maria']))->assertOk()->streamedContent();
    expect($found)->toContain('export-bidder@example.com')->not->toContain('export-admin@example.com');

    testCase()->actingAs($staff)->get(route('admin.users.export'))->assertForbidden();
});

it('lets the admin choose who to export: all users, bidders only, staff, admin or the current view', function () {
    $admin = User::create(['name' => 'Choice Admin', 'email' => 'choice-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    User::create(['name' => 'Choice Bidder', 'email' => 'choice-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Choice Builders']);

    $html = testCase()->actingAs($admin)->get(route('admin.users'))->assertOk()->getContent();
    expect($html)->toContain('data-export-choices-label="Who to include"')
        ->toContain('Bidders only')->toContain('All users')->toContain('Staff')
        ->not->toContain('What the list shows now'); // no tab or search is set

    $html = testCase()->actingAs($admin)->get(route('admin.users', ['filter' => 'bidder', 'search' => 'Choice']))->assertOk()->getContent();
    expect($html)->toContain('What the list shows now');
});

it('has no new-password field in the edit dialog, but still asks for one when creating a user', function () {
    $page = testCase()->actingAs($this->admin)->get(route('admin.users'))->assertOk();
    $html = $page->getContent();

    expect($html)->not->toContain('id="edit_password"')->and($html)->toContain('id="create_password"');
    $page->assertDontSee('set a new password');
});

it('suspends or blacklists a bidder from the edit dialog, with the order details, and lifts it by setting Active', function () {
    $user = User::create(['name' => 'Ana Reyes', 'email' => 'sanction-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Reyes Builders']);
    $user->bidderProfile()->create(['company_name' => 'Reyes Builders', 'contact_person' => 'Ana Reyes', 'contact_number' => '09171234567', 'business_address' => 'San Jose', 'approval_status' => 'approved']);
    $base = ['role' => 'bidder', 'company' => 'Reyes Builders', 'contact_number' => '09171234567', 'business_address' => 'San Jose', 'name' => 'Ana Reyes', 'email' => 'sanction-bidder@example.com'];
    $update = fn (array $data) => testCase()->actingAs($this->admin)->put(route('admin.users.update', $user), $base + $data);

    // The order's details are required.
    $update(['status' => 'blacklisted'])->assertSessionHasErrors(['sanction_reference_number', 'sanction_reason', 'sanction_authorized_by']);
    expect($user->fresh()->bidderProcurementStatus())->toBe('active');

    $update(['status' => 'blacklisted', 'sanction_reference_number' => 'BAC-ORD-2026-05', 'sanction_reason' => 'Submitted falsified documents.', 'sanction_effective_date' => now()->toDateString(), 'sanction_authorized_by' => 'Municipal Mayor'])
        ->assertSessionHasNoErrors();
    $user = $user->fresh();
    expect($user->status)->toBe('active')->and($user->bidderProcurementStatus())->toBe('blacklisted')
        ->and($user->bidderProfile->activeSanction->reference_number)->toBe('BAC-ORD-2026-05');
    expect(\App\Models\UserNotification::where('user_id', $user->id)->where('type', 'bidder_sanction')->count())->toBe(1);

    // Saving other changes with the same sanction chosen does not record it again.
    $update(['status' => 'blacklisted', 'company' => 'Reyes Builders'])->assertSessionHasNoErrors();
    expect($user->bidderProfile->sanctions()->count())->toBe(1);

    // A bidder is never sanctioned through a staff account: the status is only offered for bidders.
    $staff = User::create(['name' => 'Jose', 'email' => 'sanction-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => User::staffOfficeOptions()[0]]);
    testCase()->actingAs($this->admin)->put(route('admin.users.update', $staff), ['role' => 'staff', 'office' => $staff->office, 'name' => 'Jose', 'email' => 'sanction-staff@example.com', 'status' => 'suspended'])->assertSessionHasErrors('status');

    // Setting the bidder back to Active lifts the sanction.
    $update(['status' => 'active'])->assertSessionHasNoErrors();
    expect($user->fresh()->bidderProcurementStatus())->toBe('active')->and($user->bidderProfile->fresh()->hasActiveProcurementSanction())->toBeFalse();
    expect(\App\Models\UserNotification::where('user_id', $user->id)->where('type', 'bidder_sanction_lifted')->count())->toBe(1);
});
