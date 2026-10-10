<?php

use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Two end-user accounts of the same office are separate people: each sees
 * only the purchase requests it filed and the projects made from them.
 * Records nobody owns (no requester, or a project without a request) stay
 * with the whole office.
 */
beforeEach(function () {
    testCase()->withoutVite();

    $office = 'Office of the Municipal Mayor';
    $this->juan = User::create(['name' => 'Juan Reyes', 'email' => 'juan@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => $office]);
    $this->maria = User::create(['name' => 'Maria Santos', 'email' => 'maria@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => $office]);
    $this->staff = User::create(['name' => 'Secretariat Staff', 'email' => 'own-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'SJBAC']);

    $this->makeRequest = fn (array $attributes = []) => ProcurementRequest::create(array_merge([
        'reference_no' => 'PR-2026-'.str_pad((string) random_int(100, 999), 4, '0', STR_PAD_LEFT),
        'end_user_office' => $office,
        'requested_by' => $this->juan->id,
        'title' => 'Office chairs for the Mayor\'s Office',
        'category' => 'goods',
        'specifications' => 'Ergonomic office chairs',
        'quantity' => 10,
        'unit' => 'units',
        'estimated_cost' => 35000,
        'fund_source' => 'General Fund',
        'delivery_period' => '30 days',
        'status' => ProcurementRequest::STATUS_SUBMITTED,
    ], $attributes));
});

it('shows a purchase request only to the account that filed it', function () {
    $request = ($this->makeRequest)();

    testCase()->actingAs($this->juan)->get(route('end-user.dashboard'))->assertOk()->assertSee($request->reference_no);
    testCase()->actingAs($this->juan)->get(route('end-user.requests.index'))->assertOk()->assertSee($request->reference_no);
    testCase()->actingAs($this->juan)->get(route('end-user.requests.show', $request))->assertOk();

    testCase()->actingAs($this->maria)->get(route('end-user.dashboard'))->assertOk()->assertDontSee($request->reference_no);
    testCase()->actingAs($this->maria)->get(route('end-user.requests.index'))->assertOk()->assertDontSee($request->reference_no);
    testCase()->actingAs($this->maria)->get(route('end-user.requests.show', $request))->assertNotFound();
    testCase()->actingAs($this->maria)->get(route('end-user.requests.print', $request))->assertNotFound();
});

it('tells only the requester when the request is returned', function () {
    $request = ($this->makeRequest)();

    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), [
        'decision' => 'return', 'review_remarks' => 'Attach the canvass.',
    ])->assertSessionHasNoErrors();

    expect(UserNotification::where('user_id', $this->juan->id)->where('type', 'procurement_request')->count())->toBe(1)
        ->and(UserNotification::where('user_id', $this->maria->id)->where('type', 'procurement_request')->count())->toBe(0);
});

it('keeps the project made from a request with its requester', function () {
    $request = ($this->makeRequest)(['status' => ProcurementRequest::STATUS_IN_PROCUREMENT]);
    Project::create([
        'title' => 'Supply of office chairs', 'description' => 'Office chairs', 'budget' => 35000,
        'deadline' => now()->addWeek(), 'status' => 'open', 'procurement_request_id' => $request->id,
        'end_user_unit' => $request->end_user_office,
    ]);

    testCase()->actingAs($this->juan)->get(route('end-user.dashboard'))->assertOk()->assertSee('Supply of office chairs');
    testCase()->actingAs($this->maria)->get(route('end-user.dashboard'))->assertOk()->assertDontSee('Supply of office chairs');
});

it('shares records nobody owns with the whole office', function () {
    $legacy = ($this->makeRequest)(['requested_by' => null, 'title' => 'Request filed before accounts were tracked']);
    Project::create([
        'title' => 'Project the BAC created without a request', 'description' => 'No PR', 'budget' => 50000,
        'deadline' => now()->addWeek(), 'status' => 'open', 'end_user_unit' => 'Office of the Municipal Mayor',
    ]);

    foreach ([$this->juan, $this->maria] as $account) {
        testCase()->actingAs($account)->get(route('end-user.dashboard'))->assertOk()
            ->assertSee('Request filed before accounts were tracked')
            ->assertSee('Project the BAC created without a request');
        testCase()->actingAs($account)->get(route('end-user.requests.show', $legacy))->assertOk();
    }
});
