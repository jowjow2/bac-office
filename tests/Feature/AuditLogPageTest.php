<?php

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'audit-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->project = Project::create([
        'reference_no' => 'AUDIT-1', 'title' => 'Audit road project', 'description' => 'Audit test.',
        'budget' => 900000, 'status' => 'open', 'deadline' => now()->subHour(),
        'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009',
    ]);
});

it('lists who did what to which record, with the system shown for scheduled actions', function () {
    testCase()->actingAs($this->admin);
    AuditLog::log('project_schedule_changed', $this->project, ['deadline' => '2026-10-20 10:00:00'], ['deadline' => '2026-10-14 10:00:00']);

    // A scheduled opening during someone else's request is recorded as the system's.
    $staff = User::create(['name' => 'Visiting Staff', 'email' => 'audit-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);
    ProjectSchedule::create(['project_id' => $this->project->id, 'bid_submission_deadline' => now()->subHour(), 'bid_opening_date' => now()->subMinutes(30)]);
    testCase()->actingAs($staff)->get('/')->assertOk();
    expect(AuditLog::where('action', 'bid_technical_documents_auto_opened')->sole()->user_id)->toBeNull();

    testCase()->actingAs($this->admin)->get(route('admin.audit-logs'))->assertOk()
        ->assertSee('Audit logs')
        ->assertSee('Schedule changed')
        ->assertSee('Bids opened at the scheduled time')
        ->assertSee('AUDIT-1 Audit road project')
        ->assertSee('BAC Chair')
        ->assertSee('System')
        ->assertSee('Oct 20, 2026 10:00 AM');

    testCase()->actingAs($this->admin)->get(route('admin.audit-logs', ['actor' => 'system']))->assertOk()
        ->assertSee('Bids opened at the scheduled time')->assertDontSee('Schedule changed');
    testCase()->actingAs($this->admin)->get(route('admin.audit-logs', ['q' => 'schedule changed']))->assertOk()
        ->assertSee('Schedule changed')->assertDontSee('Bids opened at the scheduled time');
    testCase()->actingAs($this->admin)->get(route('admin.audit-logs', ['type' => 'User']))->assertOk()
        ->assertSee('No entries match these filters');
});

it('exports the filtered entries as CSV', function () {
    testCase()->actingAs($this->admin);
    AuditLog::log('project_schedule_changed', $this->project, [], ['deadline' => '2026-10-14 10:00:00']);

    $csv = testCase()->actingAs($this->admin)->get(route('admin.audit-logs.export'))->assertOk()->streamedContent();

    expect($csv)->toContain('Date and time (Asia/Manila)')
        ->toContain('Schedule changed')
        ->toContain('Project · AUDIT-1 Audit road project')
        ->toContain('BAC Chair');
});

it('is for the BAC Admin only', function () {
    $staff = User::create(['name' => 'Staff', 'email' => 'audit-staff2@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);

    testCase()->actingAs($staff)->get(route('admin.audit-logs'))->assertStatus(403);
    testCase()->actingAs($staff)->get(route('admin.audit-logs.export'))->assertStatus(403);
    testCase()->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee(route('admin.audit-logs'), false);
});
