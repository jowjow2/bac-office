<?php

use App\Models\Assignment;
use App\Models\Award;
use App\Models\Bid;
use App\Models\Project;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

it('shows a delete project action on the admin projects page', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-projects@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $project = Project::create([
        'title' => 'Medical Supplies Procurement',
        'description' => 'Purchase of medical supplies for the municipal clinic.',
        'budget' => 750000,
        'deadline' => now()->addWeek(),
        'status' => 'open',
    ]);

    $response = $test
        ->actingAs($admin)
        ->get(route('admin.projects'));

    $response->assertOk();
    $response->assertSee(route('admin.project.destroy', $project), false);
    $response->assertSee('Delete');
});

it('deletes a project and its dependent records from the admin projects page', function () {
    $test = testCase();

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-delete@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $staff = User::create([
        'name' => 'Staff User',
        'email' => 'staff-delete@example.com',
        'password' => Hash::make('password'),
        'role' => 'staff',
        'status' => 'active',
    ]);

    $bidder = User::create([
        'name' => 'Bidder User',
        'email' => 'bidder-delete@example.com',
        'password' => Hash::make('password'),
        'role' => 'bidder',
        'status' => 'active',
        'company' => 'Delete Test Company',
        'registration_no' => 'REG-DELETE-100',
    ]);

    $project = Project::create([
        'title' => 'Office Equipment Procurement',
        'description' => 'Purchase of office equipment for the BAC office.',
        'budget' => 1200000,
        'deadline' => now()->addDays(10),
        'status' => 'awarded',
    ]);

    $assignment = Assignment::create([
        'project_id' => $project->id,
        'staff_id' => $staff->id,
        'role_in_project' => 'Evaluator',
    ]);

    $bid = Bid::create([
        'user_id' => $bidder->id,
        'project_id' => $project->id,
        'bid_amount' => 1100000,
        'proposal_file' => 'uploads/proposals/delete-test.pdf',
        'status' => 'approved',
        'notes' => 'Approved for award',
    ]);

    $award = Award::create([
        'project_id' => $project->id,
        'bid_id' => $bid->id,
        'contract_amount' => 1100000,
        'contract_date' => now()->toDateString(),
        'status' => 'active',
        'notes' => 'Awarded to the bidder',
    ]);

    $response = $test
        ->actingAs($admin)
        ->delete(route('admin.project.destroy', $project));

    $response->assertRedirect(route('admin.projects'));
    $response->assertSessionHas('success', 'Project deleted successfully.');

    $test->assertDatabaseMissing('projects', ['id' => $project->id]);
    $test->assertDatabaseMissing('bids', ['id' => $bid->id]);
    $test->assertDatabaseMissing('awards', ['id' => $award->id]);
    $test->assertDatabaseMissing('staff_assignments', ['id' => $assignment->id]);
});

it('returns a linked purchase request to the BAC queue when its project is deleted', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin-request-delete@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);
    $procurementRequest = ProcurementRequest::create([
        'reference_no' => 'PR-2026-0099',
        'end_user_office' => 'Municipal Engineering Office',
        'requested_by' => $admin->id,
        'title' => 'Deleted project request',
        'category' => 'goods',
        'specifications' => 'Ten units of office equipment',
        'quantity' => 10,
        'unit' => 'units',
        'estimated_cost' => 500000,
        'fund_source' => 'General Fund',
        'delivery_period' => '30 days',
        'status' => ProcurementRequest::STATUS_IN_PROCUREMENT,
    ]);
    $project = Project::create([
        'procurement_request_id' => $procurementRequest->id,
        'title' => 'Deleted project request',
        'description' => 'Project linked to an active purchase request.',
        'budget' => 500000,
        'status' => 'draft',
    ]);

    testCase()->actingAs($admin)->delete(route('admin.project.destroy', $project))->assertRedirect(route('admin.projects'));

    testCase()->assertDatabaseMissing('projects', ['id' => $project->id]);
    testCase()->assertDatabaseHas('procurement_requests', [
        'id' => $procurementRequest->id,
        'status' => ProcurementRequest::STATUS_FORWARDED,
    ]);
    testCase()->assertDatabaseHas('audit_logs', [
        'action' => 'procurement_project_deleted',
        'auditable_id' => $procurementRequest->id,
    ]);
    testCase()->actingAs($admin)->get(route('admin.requests', ['tab' => 'procurement']))->assertOk()->assertDontSee('PR-2026-0099');
    testCase()->get(route('admin.requests', ['tab' => 'bac']))->assertOk()->assertSee('PR-2026-0099');
});