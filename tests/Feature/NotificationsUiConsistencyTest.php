<?php

use App\Models\User;
use App\Support\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

function uiConsistencyUser(string $role): User
{
    $user = User::create([
        'name' => ucfirst($role) . ' UI User',
        'email' => $role . '-ui@example.com',
        'password' => Hash::make('secret123'),
        'role' => $role,
        'status' => 'active',
        'company' => $role === 'bidder' ? 'UI Consistency Inc.' : null,
        'office' => $role === 'staff' ? 'BAC Office' : null,
    ]);

    if ($role === 'bidder') {
        $user->bidderProfile()->create([
            'company_name' => $user->company,
            'contact_person' => $user->name,
            'contact_number' => '09171234567',
            'business_address' => 'BAC Avenue',
            'approval_status' => 'approved',
            'review_status' => 'approved',
        ]);
    }

    return $user;
}

it('renders identical notification panel/list markup across admin, staff, and bidder', function () {
    $admin = uiConsistencyUser('admin');
    $staff = uiConsistencyUser('staff');
    $bidder = uiConsistencyUser('bidder');

    SystemNotification::createForUser($admin->id, 'Assignment update', 'A new task was assigned to you.', 'staff_assignment');
    SystemNotification::createForUser($staff->id, 'Assignment update', 'A new task was assigned to you.', 'staff_assignment');
    SystemNotification::createForUser($bidder->id, 'Assignment update', 'A new task was assigned to you.', 'staff_assignment');

    $adminResponse = testCase()->actingAs($admin)->get(route('admin.notifications'));
    $staffResponse = testCase()->actingAs($staff)->get(route('staff.notifications'));
    $bidderResponse = testCase()->actingAs($bidder)->get(route('bidder.notifications'));

    $adminResponse->assertOk();
    $staffResponse->assertOk();
    $bidderResponse->assertOk();

    // End-user offices get the same center.
    $office = User::create(['name' => 'Office UI User', 'email' => 'office-ui@example.com', 'password' => Hash::make('secret123'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    SystemNotification::createForUser($office->id, 'Assignment update', 'A new task was assigned to you.', 'staff_assignment');
    $officeResponse = testCase()->actingAs($office)->get(route('end-user.notifications'))->assertOk();

    foreach ([$adminResponse, $staffResponse, $bidderResponse, $officeResponse] as $response) {
        $response->assertSee('class="nc" data-nc', false)
            ->assertSee('data-notifications-read-all', false)
            ->assertSee('data-notifications-list data-nc-list', false)
            ->assertSeeInOrder(['Today', 'Assignment update', 'Assignment', 'A new task was assigned to you.'])
            ->assertSee('nc-icon nc-icon--info', false)
            ->assertSee('fa-user-tag', false)
            ->assertSee('1 unread');
    }
});
