<?php

use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();

    $this->admin = User::create([
        'name' => 'Projects Admin',
        'email' => 'projects-admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'status' => 'active',
    ]);
});

it('filters from the five summary cards and keeps filtered pagination counts accurate', function () {
    foreach ([
        ['Draft Project ' . 1, 'draft'],
        ['Draft Project ' . 2, 'draft'],
        ['Open Project', 'open'],
        ['Closed Project', 'closed'],
        ['Awarded Project', 'awarded'],
    ] as [$title, $status]) {
        Project::create([
            'title' => $title,
            'description' => $title,
            'budget' => 100000,
            'deadline' => now()->addDays(10),
            'status' => $status,
        ]);
    }

    $response = $this->actingAs($this->admin)->get(route('admin.projects', [
        'status' => 'draft',
    ]));

    $response->assertOk()
        ->assertSee('Drafts')
        ->assertSee('Draft Project 1')
        ->assertDontSee('Open Project')
        ->assertSee('class="projects-summary-card projects-summary-card--draft is-active"', false)
        ->assertSee('Showing 1&ndash;2 of 2 projects', false)
        ->assertDontSee('class="projects-drafts-link"', false);

    expect($response->viewData('projects')->total())->toBe(2);
});

it('shows past-deadline open projects as closed for submission and flags urgent unassigned staffing', function () {
    $overdue = Project::create([
        'title' => 'Overdue Open Project',
        'description' => 'Past deadline',
        'budget' => 100000,
        'deadline' => now()->subDay(),
        'status' => 'open',
    ]);

    $dueToday = Project::create([
        'title' => 'Due Today Project',
        'description' => 'Due today',
        'budget' => 100000,
        'deadline' => now()->startOfDay(),
        'status' => 'open',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.projects'));

    $response->assertOk()
        ->assertSee('Awaiting opening')
        ->assertSee('Submission closed')
        ->assertSee('Urgent staffing needed')
        ->assertSee('Due today')
        ->assertSee('Due Today Project')
        ->assertSee('Overdue Open Project');

    expect($overdue->fresh()->status)->toBe('open')
        ->and($dueToday->fresh()->status)->toBe('open');
});

it('shows a bid anomaly link for projects with bids over 500 percent above budget', function () {
    $bidder = User::create([
        'name' => 'Anomaly Bidder',
        'email' => 'anomaly-bidder@example.com',
        'password' => bcrypt('password'),
        'role' => 'bidder',
        'status' => 'active',
    ]);

    $project = Project::create([
        'title' => 'Anomalous Bid Project',
        'description' => 'Needs bid review',
        'budget' => 100000,
        'deadline' => now()->addDays(10),
        'status' => 'open',
    ]);

    Bid::create([
        'user_id' => $bidder->id,
        'project_id' => $project->id,
        'bid_amount' => 700000,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.projects'));

    $response->assertOk()
        ->assertSee('Unusual bid variance', false)
        ->assertSee(route('admin.bids', ['project' => $project->id]), false)
        ->assertSee('More actions: Edit, Archive, Delete', false)
        ->assertSee('View project details', false);
});

it('assigns staff straight from an Unassigned chip and returns to the list', function () {
    $staff = User::create(['name' => 'Rhea Santos', 'email' => 'quick-staff@example.com', 'password' => bcrypt('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'BAC Secretariat']);
    $project = Project::create(['title' => 'Due Today Road', 'description' => 'x', 'budget' => 100000, 'deadline' => now()->addHours(2), 'status' => 'open']);

    $this->actingAs($this->admin)->get(route('admin.projects'))->assertOk()
        ->assertSee('data-quick-assign="'.$project->id.'"', false)
        ->assertSee('id="quickAssignDialog"', false)
        ->assertSee('Rhea Santos · BAC Secretariat');

    $this->actingAs($this->admin)->from(route('admin.projects'))->post(route('admin.assignments.store'), [
        'staff_id' => $staff->id, 'project_id' => $project->id, 'return' => 'projects',
    ])->assertRedirect(route('admin.projects'))->assertSessionHas('success', 'Rhea Santos assigned to Due Today Road.');

    // Assigned now: the name replaces the chip, and assigning again is refused back on the list.
    $this->actingAs($this->admin)->get(route('admin.projects'))
        ->assertDontSee('data-quick-assign="'.$project->id.'"', false)->assertSee('Rhea Santos');
    $this->actingAs($this->admin)->from(route('admin.projects'))->post(route('admin.assignments.store'), [
        'staff_id' => $staff->id, 'project_id' => $project->id, 'return' => 'projects',
    ])->assertRedirect(route('admin.projects'))->assertSessionHasErrors('staff_id');
});

it('shows schedule warnings as their own card, apart from a short success message', function () {
    $this->actingAs($this->admin)
        ->withSession(['success' => 'Project created successfully.', 'schedule_warnings' => ['The bid submission deadline is less than 7 calendar days after the local BAC publication.']])
        ->get(route('admin.projects'))->assertOk()
        ->assertSee('Project created successfully.')
        ->assertSee('class="schedule-warning-card"', false)
        ->assertSee('less than 7 calendar days')
        ->assertSee('recorded in the audit log');
});
