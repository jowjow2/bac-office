<?php

use App\Models\AuditLog;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'prd-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'Secretariat', 'email' => 'prd-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'BAC Secretariat']);
    $this->makeRequest = fn (string $ref, string $title) => ProcurementRequest::create([
        'reference_no' => $ref, 'end_user_office' => 'Municipal Agriculture Office', 'requested_by' => null,
        'title' => $title, 'category' => 'goods', 'specifications' => 'Test', 'quantity' => 1, 'unit' => 'lot',
        'estimated_cost' => 1500000, 'fund_source' => 'General Fund', 'delivery_period' => '30 days',
        'status' => ProcurementRequest::STATUS_FORWARDED, 'submitted_at' => now()->subDay(), 'forwarded_at' => now(),
    ]);
});

it('lets the BAC admin delete a mistaken purchase request with a reason, ', function () {
    $request = ($this->makeRequest)('PR-2026-0001', 'asdasd');
    $request->documents()->create(['document_type' => 'other', 'original_name' => 'quote.pdf', 'file_path' => 'procurement-requests/quote.pdf', 'uploaded_by' => $this->admin->id]);

    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']))->assertOk()
        ->assertSee('data-dialog-open="delete-request-'.$request->id.'"', false)
        ->assertSee('Delete this purchase request?');

    // A reason is required.
    testCase()->actingAs($this->admin)->from(route('admin.requests', ['tab' => 'bac']))
        ->delete(route('admin.requests.destroy', $request), ['reason' => ''])->assertSessionHasErrors('reason');
    expect(ProcurementRequest::find($request->id))->not->toBeNull();

    testCase()->actingAs($this->admin)->delete(route('admin.requests.destroy', $request), ['reason' => 'Test entry', 'tab' => 'bac'])
        ->assertRedirect(route('admin.requests', ['tab' => 'bac']))
        ->assertSessionHas('success', 'PR-2026-0001 was deleted. The reason is kept in the audit log.');

    $log = AuditLog::where('action', 'procurement_request_deleted')->sole();
    expect(ProcurementRequest::find($request->id))->toBeNull()
        ->and($request->documents()->count())->toBe(0)
        ->and($log->new_values['reason'])->toBe('Test entry')
        ->and($log->old_values['title'])->toBe('asdasd');
});

it('never deletes a request that has a procurement project, and only the admin may delete', function () {
    $request = ($this->makeRequest)('PR-2026-0002', 'Office chairs');
    Project::create(['title' => 'Office chairs', 'description' => 'Chairs.', 'reference_no' => 'SJ-BAC-2026-G-050', 'category' => 'goods', 'procurement_mode' => 'small_value_procurement', 'budget' => 3500, 'status' => 'draft', 'procurement_request_id' => $request->id]);

    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'all']))->assertOk()
        ->assertDontSee('data-dialog-open="delete-request-'.$request->id.'"', false);
    testCase()->actingAs($this->admin)->delete(route('admin.requests.destroy', $request), ['reason' => 'x'])->assertSessionHas('error');
    expect(ProcurementRequest::find($request->id))->not->toBeNull();

    $other = ($this->makeRequest)('PR-2026-0003', 'Other');
    testCase()->actingAs($this->staff)->delete(route('admin.requests.destroy', $other), ['reason' => 'x']);
    expect(ProcurementRequest::find($other->id))->not->toBeNull();
});
