<?php

use App\Models\Award;
use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();

    $user = fn (string $email, string $role, array $extra = []) => User::create(array_merge([
        'name' => ucfirst($role).' User', 'email' => $email, 'password' => Hash::make('password'), 'role' => $role, 'status' => 'active',
    ], $extra));
    $this->admin = $user('awr-admin@example.com', 'admin');
    $this->staff = $user('awr-staff@example.com', 'staff');
    $bidder = $user('awr-bidder@example.com', 'bidder', ['name' => 'Juan Builder', 'company' => 'Mindoro Builders']);

    $project = Project::create([
        'title' => 'Concreting of Barangay Road', 'description' => 'Road works', 'reference_no' => 'SJ-BAC-2026-I-300',
        'category' => 'infrastructure', 'procurement_mode' => 'public_bidding', 'budget' => 2000000,
        'deadline' => now()->subDays(10), 'status' => 'awarded',
    ]);
    $bid = Bid::create(['user_id' => $bidder->id, 'project_id' => $project->id, 'bid_amount' => 1800000, 'proposal_file' => 'uploads/p.pdf', 'status' => 'approved']);
    $this->award = Award::create(['project_id' => $project->id, 'bid_id' => $bid->id, 'contract_amount' => 1800000, 'contract_date' => now()->toDateString(), 'status' => 'active']);
});

it('puts Export and Report in the awards page header', function () {
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()
        ->assertSee(route('admin.awards.export'), false)
        ->assertSee(route('admin.awards.report'), false)
        ->assertSee('data-report-dialog="awardsReport"', false);
});

it('exports the awards register as CSV', function () {
    $response = testCase()->actingAs($this->admin)->get(route('admin.awards.export'))->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())
        ->toContain('Winning bidder')->toContain('Concreting of Barangay Road')->toContain('SJ-BAC-2026-I-300')->toContain('1800000.00');

    testCase()->actingAs($this->staff)->get(route('admin.awards.export'))->assertForbidden();
});

it('prints an awards report with totals and the register', function () {
    testCase()->actingAs($this->admin)->get(route('admin.awards.report'))->assertOk()
        ->assertSee('Awards and contracts report')->assertSee('₱1,800,000.00')->assertSee('Concreting of Barangay Road')->assertSee('Public Bidding');

    // A period with no awards says so instead of showing an empty table.
    testCase()->actingAs($this->admin)->get(route('admin.awards.report', ['from' => '2020-01-01', 'to' => '2020-12-31']))->assertOk()
        ->assertSee('No awards were recorded in this period.');

    testCase()->actingAs($this->staff)->get(route('admin.awards.report'))->assertForbidden();
});
