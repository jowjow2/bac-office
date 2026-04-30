<?php

use App\Models\Assignment;
use App\Models\Award;
use App\Models\Bid;
use App\Models\Project;
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
