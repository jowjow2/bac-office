<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

function createMessagingAdmin(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Messaging Admin',
        'email' => 'messaging-admin@example.com',
        'password' => Hash::make('secret123'),
        'role' => 'admin',
        'status' => 'active',
    ], $overrides));
}

function createMessagingBidder(array $overrides = []): User
{
    $user = User::create(array_merge([
        'name' => 'Messaging Bidder',
        'email' => 'messaging-bidder@example.com',
        'username' => 'messaging-bidder',
        'password' => Hash::make('secret123'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Messaging Builders Inc.',
        'registration_no' => 'REG-MSG-1001',
    ], $overrides));

    $user->bidderProfile()->create([
        'company_name' => $user->company ?: 'Messaging Builders Inc.',
        'contact_number' => '09171234567',
        'business_address' => 'San Jose, Occidental Mindoro',
        'approval_status' => 'approved',
        'approved_at' => now(),
    ]);

    return $user;
}

function createMessagingStaff(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Messaging Staff',
        'email' => 'messaging-staff@example.com',
        'password' => Hash::make('secret123'),
        'role' => 'staff',
        'status' => 'active',
        'office' => 'BAC Office',
    ], $overrides));
}

it('shows message shortcuts in the staff dashboard and sidebar', function () {
    $staff = createMessagingStaff();

    $response = testCase()
        ->actingAs($staff)
        ->get(route('staff.dashboard'));

    $response->assertOk();
    $response->assertSee(route('staff.messages'), false);
    $response->assertSee('Messages');
    $response->assertSee('Contact admin and bidders');
    $response->assertSee('Open Messages');
});

it('shows staff message tabs for admin and bidder conversations', function () {
    $staff = createMessagingStaff();
    createMessagingAdmin();
    createMessagingBidder();

    $adminTab = testCase()
        ->actingAs($staff)
        ->get(route('staff.messages', ['tab' => 'admin']));

    $adminTab->assertOk();
    $adminTab->assertSee('Send updates and communicate with admin or bidders');
    $adminTab->assertSee('Admin');
    $adminTab->assertSee('Bidders');
    $adminTab->assertSee('Message Admin');
    $adminTab->assertSee(route('staff.messages.store'), false);

    testCase()
        ->actingAs($staff)
        ->get(route('staff.messages', ['tab' => 'bidders']))
        ->assertOk()
        ->assertSee('Message Bidder');
});

it('shows message shortcuts in the admin and bidder dashboards', function () {
    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();

    Message::create([
        'sender_id' => $bidder->id,
        'recipient_id' => $admin->id,
        'body' => 'Can you help with our submission?',
    ]);

    $adminResponse = testCase()
        ->actingAs($admin)
        ->get(route('admin.dashboard'));

    $adminResponse->assertOk();
    $adminResponse->assertSee(route('admin.messages'), false);
    $adminResponse->assertSee('Messages');

    $bidderResponse = testCase()
        ->actingAs($bidder)
        ->get(route('bidder.dashboard'));

    $bidderResponse->assertOk();
    $bidderResponse->assertSee(route('bidder.messages'), false);
});

it('lets admins send messages to bidders and creates a bidder notification', function () {
    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();

    $response = testCase()
        ->actingAs($admin)
        ->post(route('admin.messages.store'), [
            'recipient_id' => $bidder->id,
            'body' => 'Your registration documents are now under review.',
        ]);

    $response->assertRedirect(route('admin.messages', ['user' => $bidder->id, 'tab' => 'bidders']));

    testCase()->assertDatabaseHas('messages', [
        'sender_id' => $admin->id,
        'recipient_id' => $bidder->id,
        'body' => 'Your registration documents are now under review.',
    ]);

    testCase()->assertDatabaseHas('user_notifications', [
        'user_id' => $bidder->id,
        'title' => 'New message from BAC Office',
        'type' => 'message',
    ]);
});

it('lets staff send messages to admins and bidders', function () {
    $staff = createMessagingStaff();
    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();

    testCase()
        ->actingAs($staff)
        ->post(route('staff.messages.store'), [
            'recipient_id' => $admin->id,
            'body' => 'The bid review queue needs your confirmation.',
        ])
        ->assertRedirect(route('staff.messages', ['user' => $admin->id, 'tab' => 'admin']));

    testCase()->assertDatabaseHas('messages', [
        'sender_id' => $staff->id,
        'recipient_id' => $admin->id,
        'body' => 'The bid review queue needs your confirmation.',
    ]);

    testCase()
        ->actingAs($staff)
        ->post(route('staff.messages.store'), [
            'recipient_id' => $bidder->id,
            'body' => 'Please clarify your eligibility attachment.',
        ])
        ->assertRedirect(route('staff.messages', ['user' => $bidder->id, 'tab' => 'bidders']));

    testCase()->assertDatabaseHas('messages', [
        'sender_id' => $staff->id,
        'recipient_id' => $bidder->id,
        'body' => 'Please clarify your eligibility attachment.',
    ]);

    testCase()->assertDatabaseHas('user_notifications', [
        'user_id' => $admin->id,
        'title' => 'New staff message',
        'type' => 'message',
    ]);

    testCase()->assertDatabaseHas('user_notifications', [
        'user_id' => $bidder->id,
        'title' => 'New staff message',
        'type' => 'message',
    ]);
});

it('lets admins and bidders see staff conversations', function () {
    $admin = createMessagingAdmin();
    $staff = createMessagingStaff();
    $bidder = createMessagingBidder();

    Message::create([
        'sender_id' => $staff->id,
        'recipient_id' => $admin->id,
        'body' => 'Staff update for admin review.',
    ]);

    Message::create([
        'sender_id' => $staff->id,
        'recipient_id' => $bidder->id,
        'body' => 'Staff update for bidder review.',
    ]);

    testCase()
        ->actingAs($admin)
        ->get(route('admin.messages', ['user' => $staff->id]))
        ->assertOk()
        ->assertSee('Staff update for admin review.')
        ->assertSee('Messaging Staff');

    testCase()
        ->actingAs($bidder)
        ->get(route('bidder.messages', ['user' => $staff->id]))
        ->assertOk()
        ->assertSee('Staff update for bidder review.')
        ->assertSee('Messaging Staff');
});

it('lets bidders reply to admins and marks incoming admin messages as read when opened', function () {
    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();

    $incomingMessage = Message::create([
        'sender_id' => $admin->id,
        'recipient_id' => $bidder->id,
        'body' => 'Please upload your latest permit copy.',
    ]);

    $bidderThreadResponse = testCase()
        ->actingAs($bidder)
        ->get(route('bidder.messages', ['user' => $admin->id]));

    $bidderThreadResponse->assertOk();
    $bidderThreadResponse->assertSee('Please upload your latest permit copy.');

    expect($incomingMessage->fresh()?->read_at)->not->toBeNull();

    $replyResponse = testCase()
        ->actingAs($bidder)
        ->post(route('bidder.messages.store'), [
            'recipient_id' => $admin->id,
            'body' => 'We uploaded the new permit this afternoon.',
        ]);

    $replyResponse->assertRedirect(route('bidder.messages', ['user' => $admin->id, 'tab' => 'admin']));

    testCase()->assertDatabaseHas('messages', [
        'sender_id' => $bidder->id,
        'recipient_id' => $admin->id,
        'body' => 'We uploaded the new permit this afternoon.',
    ]);

    testCase()->assertDatabaseHas('user_notifications', [
        'user_id' => $admin->id,
        'title' => 'New bidder message',
        'type' => 'message',
    ]);
});

it('returns admin online statuses for bidder message polling', function () {
    $activeAdmin = createMessagingAdmin([
        'email' => 'active-admin@example.com',
    ]);
    $offlineAdmin = createMessagingAdmin([
        'email' => 'offline-admin@example.com',
    ]);
    $bidder = createMessagingBidder();

    DB::table('sessions')->insert([
        [
            'id' => 'active-admin-session',
            'user_id' => $activeAdmin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => '',
            'last_activity' => now()->subMinute()->timestamp,
        ],
        [
            'id' => 'offline-admin-session',
            'user_id' => $offlineAdmin->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => '',
            'last_activity' => now()->subMinutes(10)->timestamp,
        ],
    ]);

    $response = testCase()
        ->actingAs($bidder)
        ->getJson(route('bidder.messages.status-sync'));

    $response->assertOk();
    $response->assertJsonPath("statuses.{$activeAdmin->id}", true);
    $response->assertJsonPath("statuses.{$offlineAdmin->id}", false);
});

it('returns bidder online statuses for admin message polling', function () {
    $admin = createMessagingAdmin();
    $activeBidder = createMessagingBidder([
        'email' => 'active-bidder@example.com',
        'username' => 'active-bidder',
        'company' => 'Active Builders Inc.',
        'registration_no' => 'REG-ACTIVE-1001',
    ]);
    $offlineBidder = createMessagingBidder([
        'email' => 'offline-bidder@example.com',
        'username' => 'offline-bidder',
        'company' => 'Offline Builders Inc.',
        'registration_no' => 'REG-OFFLINE-1001',
    ]);

    DB::table('sessions')->insert([
        [
            'id' => 'active-bidder-session',
            'user_id' => $activeBidder->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => '',
            'last_activity' => now()->subMinute()->timestamp,
        ],
        [
            'id' => 'offline-bidder-session',
            'user_id' => $offlineBidder->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => '',
            'last_activity' => now()->subMinutes(10)->timestamp,
        ],
    ]);

    $response = testCase()
        ->actingAs($admin)
        ->getJson(route('admin.messages.status-sync'));

    $response->assertOk();
    $response->assertJsonPath("statuses.{$activeBidder->id}", true);
    $response->assertJsonPath("statuses.{$offlineBidder->id}", false);
});

it('syncs conversation messages for live polling and marks incoming messages as read', function () {
    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();

    $olderMessage = Message::create([
        'sender_id' => $bidder->id,
        'recipient_id' => $admin->id,
        'body' => 'Earlier question',
    ]);

    $incomingMessage = Message::create([
        'sender_id' => $admin->id,
        'recipient_id' => $bidder->id,
        'body' => 'Please review the new schedule.',
    ]);

    $response = testCase()
        ->actingAs($bidder)
        ->getJson(route('bidder.messages.conversation-sync', [
            'user' => $admin->id,
            'after_id' => $olderMessage->id,
        ]));

    $response->assertOk();
    $response->assertJsonPath('ok', true);
    $response->assertJsonPath('messages.0.id', $incomingMessage->id);
    $response->assertJsonPath('messages.0.body', 'Please review the new schedule.');
    $response->assertJsonPath('messages.0.is_outgoing', false);

    expect($incomingMessage->fresh()?->read_at)->not->toBeNull();
});

it('shares typing state between admin and bidder live conversations', function () {
    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();

    $typingResponse = testCase()
        ->actingAs($admin)
        ->postJson(route('admin.messages.typing'), [
            'recipient_id' => $bidder->id,
            'is_typing' => true,
        ]);

    $typingResponse->assertOk();
    $typingResponse->assertJsonPath('ok', true);

    $syncResponse = testCase()
        ->actingAs($bidder)
        ->getJson(route('bidder.messages.conversation-sync', [
            'user' => $admin->id,
        ]));

    $syncResponse->assertOk();
    $syncResponse->assertJsonPath('typing.is_typing', true);
    $syncResponse->assertJsonPath('typing.label', 'BAC is typing...');

    testCase()
        ->actingAs($admin)
        ->postJson(route('admin.messages.typing'), [
            'recipient_id' => $bidder->id,
            'is_typing' => false,
        ])
        ->assertOk();

    testCase()
        ->actingAs($bidder)
        ->getJson(route('bidder.messages.conversation-sync', [
            'user' => $admin->id,
        ]))
        ->assertJsonPath('typing.is_typing', false);
});

it('shares typing state for staff conversations', function () {
    $staff = createMessagingStaff();
    $bidder = createMessagingBidder();

    testCase()
        ->actingAs($staff)
        ->postJson(route('staff.messages.typing'), [
            'recipient_id' => $bidder->id,
            'is_typing' => true,
        ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    testCase()
        ->actingAs($bidder)
        ->getJson(route('bidder.messages.conversation-sync', [
            'user' => $staff->id,
        ]))
        ->assertOk()
        ->assertJsonPath('typing.is_typing', true)
        ->assertJsonPath('typing.label', 'Messaging Staff is typing...');
});

it('shows seen for outgoing messages that have been read', function () {
    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();

    Message::create([
        'sender_id' => $admin->id,
        'recipient_id' => $bidder->id,
        'body' => 'Your documents have been reviewed.',
        'read_at' => now(),
    ]);

    $response = testCase()
        ->actingAs($admin)
        ->get(route('admin.messages', ['user' => $bidder->id]));

    $response->assertOk();
    $response->assertSee('Your documents have been reviewed.');
    $response->assertSee('Seen');
});

it('lets admins send message attachments and only participants can open them', function () {
    Storage::fake('local');
    config(['filesystems.uploads_disk' => 'local']);

    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();
    $otherBidder = createMessagingBidder([
        'email' => 'other-bidder@example.com',
        'username' => 'other-bidder',
        'company' => 'Other Builders Inc.',
        'registration_no' => 'REG-OTHER-1001',
    ]);

    $response = testCase()
        ->actingAs($admin)
        ->post(route('admin.messages.store'), [
            'recipient_id' => $bidder->id,
            'body' => '',
            'attachment' => UploadedFile::fake()->create('bid-review.pdf', 128, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('message.attachment.name', 'bid-review.pdf');

    $message = Message::query()->latest('id')->firstOrFail();

    expect($message->attachment_kind)->toBe('file');
    expect($message->body)->toBe('');

    Storage::disk('local')->assertExists($message->attachment_path);

    testCase()
        ->actingAs($bidder)
        ->get(route('messages.attachment', $message))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    testCase()
        ->actingAs($otherBidder)
        ->get(route('messages.attachment', $message))
        ->assertForbidden();
});

it('lets bidders send photo attachments to admins', function () {
    Storage::fake('local');
    config(['filesystems.uploads_disk' => 'local']);

    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();

    $response = testCase()
        ->actingAs($bidder)
        ->post(route('bidder.messages.store'), [
            'recipient_id' => $admin->id,
            'body' => 'Photo from the site visit.',
            'attachment' => UploadedFile::fake()->create('site-visit.png', 64, 'image/png'),
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('message.attachment.kind', 'image');
    $response->assertJsonPath('message.attachment.name', 'site-visit.png');

    $message = Message::query()->latest('id')->firstOrFail();

    Storage::disk('local')->assertExists($message->attachment_path);

    testCase()
        ->actingAs($admin)
        ->get(route('messages.attachment', $message))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('rejects unsafe chat attachment types', function () {
    Storage::fake('local');
    config(['filesystems.uploads_disk' => 'local']);

    $admin = createMessagingAdmin();
    $bidder = createMessagingBidder();

    $response = testCase()
        ->actingAs($admin)
        ->post(route('admin.messages.store'), [
            'recipient_id' => $bidder->id,
            'body' => 'Please review this file.',
            'attachment' => UploadedFile::fake()->create('payload.exe', 64, 'application/x-msdownload'),
        ], [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('attachment');

    testCase()->assertDatabaseMissing('messages', [
        'body' => 'Please review this file.',
    ]);
});
