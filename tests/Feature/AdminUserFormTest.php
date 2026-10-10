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
