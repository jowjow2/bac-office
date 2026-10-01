<?php

use App\Models\Message;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\SystemNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function notificationUser(string $role, array $overrides = []): User
{
    return User::create(array_merge([
        'name' => ucfirst($role) . ' User',
        'email' => $role . '-notification@example.com',
        'password' => Hash::make('secret123'),
        'role' => $role,
        'status' => 'active',
        'company' => $role === 'bidder' ? 'Notification Builders Inc.' : null,
        'office' => $role === 'staff' ? 'BAC Office' : null,
    ], $overrides));
}

it('returns only important notifications in the live feed', function () {
    $admin = notificationUser('admin');
    $staff = notificationUser('staff');

    SystemNotification::createForUser(
        $admin->id,
        'New staff message',
        'Staff sent a message.',
        'message',
        ['sender_id' => $staff->id]
    );

    SystemNotification::createForUser(
        $admin->id,
        'Uploaded document',
        'A bidder uploaded a profile document.',
        'bidder_document',
        ['user_id' => $staff->id]
    );

    testCase()->assertDatabaseCount('user_notifications', 2);

    testCase()
        ->actingAs($admin)
        ->getJson(route('notifications.feed'))
        ->assertOk()
        ->assertJsonPath('unread_count', 2)
        ->assertJsonCount(2, 'notifications')
        ->assertJsonFragment(['type' => 'message'])
        ->assertJsonFragment(['url' => route('admin.messages', ['user' => $staff->id, 'tab' => 'staff'])]);
});

it('returns unread message count in the live feed', function () {
    $staff = notificationUser('staff');
    $admin = notificationUser('admin');

    Message::create([
        'sender_id' => $admin->id,
        'recipient_id' => $staff->id,
        'body' => 'Please review this bidder question.',
    ]);

    Message::create([
        'sender_id' => $admin->id,
        'recipient_id' => $staff->id,
        'body' => 'Already opened.',
        'read_at' => now(),
    ]);

    testCase()
        ->actingAs($staff)
        ->getJson(route('notifications.feed'))
        ->assertOk()
        ->assertJsonPath('unread_messages_count', 1);
});

it('opens a notification target and marks it read', function () {
    $admin = notificationUser('admin');
    $staff = notificationUser('staff');

    SystemNotification::createForUser(
        $admin->id,
        'New staff message',
        'Staff sent a message.',
        'message',
        ['sender_id' => $staff->id]
    );

    $notification = UserNotification::query()->firstOrFail();

    testCase()
        ->actingAs($admin)
        ->get(route('notifications.open', $notification))
        ->assertRedirect(route('admin.messages', ['user' => $staff->id, 'tab' => 'staff']));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('marks all important notifications read over ajax', function () {
    $bidder = notificationUser('bidder');
    $admin = notificationUser('admin');

    SystemNotification::createForUser(
        $bidder->id,
        'New message from BAC Office',
        'You received a new message.',
        'message',
        ['sender_id' => $admin->id]
    );

    SystemNotification::createForUser(
        $bidder->id,
        'Bid approved',
        'Your bid has been approved.',
        'bid_approved',
        ['project_id' => 1]
    );

    testCase()
        ->actingAs($bidder)
        ->postJson(route('notifications.read-all'))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect(UserNotification::query()->whereNull('read_at')->count())->toBe(0);
});
