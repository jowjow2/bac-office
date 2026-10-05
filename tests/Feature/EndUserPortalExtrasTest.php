<?php

use App\Models\Message;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * End-user office extras: messages with the BAC, duplicating a request,
 * the printable PR form, the year filter and the draft reminder.
 */
beforeEach(function () {
    testCase()->withoutVite();

    $office = 'Municipal Engineering Office';
    $this->juan = User::create(['name' => 'Juan Reyes', 'email' => 'x-juan@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => $office]);
    $this->maria = User::create(['name' => 'Maria Santos', 'email' => 'x-maria@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => $office]);
    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'x-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'Secretariat Staff', 'email' => 'x-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'SJBAC']);
    $this->bidder = User::create(['name' => 'Pedro Lim', 'email' => 'x-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Builders']);

    $this->makeRequest = fn (array $attributes = []) => ProcurementRequest::create(array_merge([
        'reference_no' => 'PR-2026-'.str_pad((string) random_int(100, 999), 4, '0', STR_PAD_LEFT),
        'end_user_office' => $office,
        'requested_by' => $this->juan->id,
        'title' => 'Supply of survey equipment',
        'category' => 'goods',
        'specifications' => 'Total station, 1 unit',
        'quantity' => 2,
        'unit' => 'sets',
        'estimated_cost' => 650000,
        'fund_source' => 'General Fund',
        'delivery_period' => '30 days',
        'justification' => 'Road survey works',
        'status' => ProcurementRequest::STATUS_SUBMITTED,
    ], $attributes));
});

it('lets an end-user message the BAC admin and the Secretariat, and the BAC answer back', function () {
    testCase()->actingAs($this->juan)->get(route('end-user.messages'))->assertOk()
        ->assertSee('BAC Secretariat')
        ->assertSee('Secretariat Staff')
        ->assertDontSee('Pedro Lim');

    testCase()->actingAs($this->juan)->post(route('end-user.messages.store'), ['recipient_id' => $this->staff->id, 'body' => 'Is PR-2026-0007 complete?'])
        ->assertSessionHasNoErrors();
    expect(Message::where('sender_id', $this->juan->id)->where('recipient_id', $this->staff->id)->count())->toBe(1);

    // The Secretariat sees the office account (with its office) and replies.
    testCase()->actingAs($this->staff)->get(route('staff.messages', ['tab' => 'offices']))->assertOk()
        ->assertSee('Juan Reyes')
        ->assertSee('Municipal Engineering Office');
    testCase()->actingAs($this->staff)->post(route('staff.messages.store'), ['recipient_id' => $this->juan->id, 'body' => 'Yes, forwarded.'])->assertSessionHasNoErrors();

    // Two accounts of one office never share a conversation.
    testCase()->actingAs($this->maria)->get(route('end-user.messages', ['tab' => 'staff', 'user' => $this->staff->id]))->assertOk()
        ->assertDontSee('Is PR-2026-0007 complete?')
        ->assertDontSee('Yes, forwarded.');
    testCase()->actingAs($this->juan)->get(route('end-user.messages', ['tab' => 'staff', 'user' => $this->staff->id]))->assertOk()
        ->assertSee('Yes, forwarded.');
});

it('does not let an end-user message a bidder', function () {
    testCase()->actingAs($this->juan)->post(route('end-user.messages.store'), ['recipient_id' => $this->bidder->id, 'body' => 'Hello'])
        ->assertSessionHasErrors('recipient_id');
    expect(Message::count())->toBe(0);

    testCase()->actingAs($this->bidder)->get(route('end-user.messages'))->assertForbidden();
});

it('puts end-user offices in the admin messages and the sidebar', function () {
    testCase()->actingAs($this->admin)->get(route('admin.messages', ['tab' => 'offices']))->assertOk()
        ->assertSee('End-user offices')->assertSee('Juan Reyes')->assertSee('Maria Santos');

    testCase()->actingAs($this->juan)->get(route('end-user.dashboard'))->assertOk()
        ->assertSee('Messages')
        ->assertDontSee('New purchase request</span>', false);
});

it('opens the composer with the request reference from Ask the BAC', function () {
    $request = ($this->makeRequest)();

    testCase()->actingAs($this->juan)->get(route('end-user.requests.show', $request))->assertOk()
        ->assertSee('Ask the BAC')
        ->assertSee('Print PR form')
        ->assertSee('Duplicate');

    testCase()->actingAs($this->juan)->get(route('end-user.messages', ['tab' => 'staff', 'user' => $this->staff->id, 'draft' => 'Re: '.$request->reference_no.' – '.$request->title.': ']))->assertOk()
        ->assertSee('Re: '.$request->reference_no, false);
});

it('duplicates a request into a new draft owned by the same account', function () {
    $request = ($this->makeRequest)(['review_remarks' => 'Old remark', 'ppmp_reference' => 'PPMP-1']);

    testCase()->actingAs($this->maria)->post(route('end-user.requests.duplicate', $request))->assertNotFound();

    $response = testCase()->actingAs($this->juan)->post(route('end-user.requests.duplicate', $request));
    $copy = ProcurementRequest::where('id', '!=', $request->id)->firstOrFail();
    $response->assertRedirect(route('end-user.requests.edit', $copy));

    expect($copy->status)->toBe(ProcurementRequest::STATUS_DRAFT)
        ->and($copy->requested_by)->toBe($this->juan->id)
        ->and($copy->reference_no)->not->toBe($request->reference_no)
        ->and($copy->title)->toBe('Supply of survey equipment')
        ->and((float) $copy->estimated_cost)->toBe(650000.0)
        ->and($copy->review_remarks)->toBeNull()
        ->and($copy->ppmp_reference)->toBeNull();
});

it('prints the purchase request form for its owner only', function () {
    $request = ($this->makeRequest)();

    testCase()->actingAs($this->juan)->get(route('end-user.requests.print', $request))->assertOk()
        ->assertSee('Purchase Request')
        ->assertSee($request->reference_no)
        ->assertSee('Supply of survey equipment')
        ->assertSee('650,000.00')
        ->assertSee('Requested by')
        ->assertSee('Approved by');

    testCase()->actingAs($this->maria)->get(route('end-user.requests.print', $request))->assertNotFound();
});

it('filters My purchase requests by year', function () {
    $old = ($this->makeRequest)(['title' => 'Request from last year']);
    $old->forceFill(['created_at' => now()->subYear()])->save();
    ($this->makeRequest)(['title' => 'Request from this year']);

    testCase()->actingAs($this->juan)->get(route('end-user.requests.index'))->assertOk()
        ->assertSee('Request from last year')->assertSee('Request from this year')->assertSee('All years');
    testCase()->actingAs($this->juan)->get(route('end-user.requests.index', ['year' => now()->year]))->assertOk()
        ->assertDontSee('Request from last year')->assertSee('Request from this year');
});

it('reminds the owner once about a draft left for a week', function () {
    $draft = ($this->makeRequest)(['status' => ProcurementRequest::STATUS_DRAFT]);
    $draft->forceFill(['updated_at' => now()->subDays(9)])->saveQuietly();

    testCase()->actingAs($this->juan)->get(route('end-user.dashboard'))->assertOk();
    testCase()->actingAs($this->juan)->get(route('end-user.dashboard'))->assertOk();

    expect(UserNotification::where('user_id', $this->juan->id)->where('title', 'Draft still waiting')->count())->toBe(1)
        ->and(UserNotification::where('user_id', $this->maria->id)->where('title', 'Draft still waiting')->count())->toBe(0);
});

it('shows the welcome card once after signing in, with the budget summary', function () {
    ($this->makeRequest)();

    testCase()->actingAs($this->juan)->withSession(['end_user_welcome' => true])->get(route('end-user.dashboard'))->assertOk()
        ->assertSee('Welcome back, Juan Reyes!')
        ->assertSee('Budget · '.now()->year)
        ->assertSee('Recent activity');
    testCase()->actingAs($this->juan)->get(route('end-user.dashboard'))->assertOk()->assertDontSee('Welcome back, Juan Reyes!');
});
