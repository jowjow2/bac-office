<?php

use App\Models\Assignment;
use App\Models\Bid;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The rebuilt portal pages of every role render from real records, show the
 * procurement mode, and make the bidder's obligations explicit.
 *
 * Set PORTAL_DUMP_DIR to also write each page's HTML (with the built assets)
 * for a visual check in a browser.
 */
beforeEach(function () {
    $dump = getenv('PORTAL_DUMP_DIR');
    if (! $dump) {
        testCase()->withoutVite();
    }

    $this->dump = function (string $name, $response) use ($dump) {
        if ($dump) {
            @mkdir($dump, 0777, true);
            // Point the built assets at the local XAMPP install for viewing.
            file_put_contents($dump.'/'.$name.'.html', str_replace('http://localhost/build/', 'http://localhost/bac-office/public/build/', $response->getContent()));
        }

        return $response;
    };

    $this->admin = User::create(['name' => 'Maria Santos', 'email' => 'portal-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'Jose Reyes', 'email' => 'portal-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'BAC Secretariat']);
    $this->office = User::create(['name' => 'Engr. Ana Cruz', 'email' => 'portal-meo@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    $this->bidder = User::create(['name' => 'Pedro Lim', 'email' => 'portal-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Builders Corp.']);

    $make = function (array $attributes, array $schedule = []): Project {
        $deadline = $attributes['deadline'] ?? workdayAt(10, 10, 0);
        $project = Project::create(array_merge([
            'description' => 'Procurement for the Municipality of San Jose.',
            'category' => 'goods',
            'legal_basis' => 'ra_12009',
            'status' => 'open',
            'deadline' => $deadline,
            'submission_mode' => Project::SUBMISSION_ELECTRONIC,
            'philgeps_reference_no' => '12005566',
        ], $attributes));
        ProjectSchedule::create(array_merge(['project_id' => $project->id, 'date_posted' => now()->subDays(3)->toDateString(), 'bid_submission_deadline' => $deadline], $schedule));

        return $project;
    };

    $this->road = $make([
        'title' => 'Concreting of Barangay Bubog farm-to-market road',
        'reference_no' => 'SJ-BAC-2026-I-004',
        'category' => 'infrastructure',
        'procurement_mode' => 'public_bidding',
        'budget' => 4800000,
        'end_user_unit' => 'Municipal Engineering Office',
        'bidding_documents_fee' => 5000,
        'payment_venue' => 'BAC Secretariat, Municipal Hall',
        'bid_security_required' => true,
        'bid_security_notes' => 'Bid Securing Declaration or 2% of the ABC in cash.',
    ], ['pre_bid_conference_date' => workdayAt(4, 10, 0), 'bid_opening_date' => workdayAt(10, 10, 30)]);

    $this->rfq = $make([
        'title' => 'Office supplies for the Treasury',
        'reference_no' => 'SJ-BAC-2026-G-011',
        'procurement_mode' => 'small_value_procurement',
        'budget' => 180000,
        'philgeps_reference_no' => null,
        'end_user_unit' => 'Municipal Treasurer\'s Office',
    ]);

    Assignment::create(['project_id' => $this->road->id, 'staff_id' => $this->staff->id]);

    Bid::create(['user_id' => $this->bidder->id, 'project_id' => $this->rfq->id, 'bid_amount' => 172500, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submitted_at' => now()->subDay(), 'submission_channel' => 'electronic', 'receipt_no' => 'RCPT-0042']);

    ProcurementRequest::create([
        'reference_no' => 'PR-2026-0031', 'end_user_office' => 'Municipal Engineering Office', 'requested_by' => $this->office->id,
        'title' => 'Survey equipment', 'category' => 'goods', 'specifications' => 'Total station, 1 unit', 'quantity' => 1, 'unit' => 'unit',
        'estimated_cost' => 650000, 'fund_source' => 'General Fund', 'delivery_period' => '30 calendar days',
        'status' => ProcurementRequest::STATUS_SUBMITTED, 'submitted_at' => now()->subDays(2),
    ]);
});

it('renders the admin procurement overview with KPIs, pipeline, register and upcoming dates', function () {
    ($this->dump)('admin-dashboard', testCase()->actingAs($this->admin)->get(route('admin.dashboard')))
        ->assertOk()
        ->assertSee('Procurement overview')
        ->assertSee('aria-label="Filter by stage"', false)
        ->assertSee('SJ-BAC-2026-I-004')
        ->assertSee('PR-2026-0031')
        ->assertSee('Competitive')
        ->assertSee('SVP')
        ->assertSee('Upcoming · next 14 days')
        ->assertSee('Pre-bid conference');
});

it('renders the staff overview scoped to the assigned projects', function () {
    ($this->dump)('staff-dashboard', testCase()->actingAs($this->staff)->get(route('staff.dashboard')))
        ->assertOk()
        ->assertSee('SJ-BAC-2026-I-004')
        ->assertDontSee('SJ-BAC-2026-G-011')
        ->assertSee('PR-2026-0031');
});

it('renders the procurement record with the mode, next action, responsible office and audit history', function () {
    ($this->dump)('admin-record-rfq', testCase()->actingAs($this->admin)->get(route('admin.procurement.show', $this->rfq)))
        ->assertOk()
        ->assertSee('Small Value Procurement')
        ->assertSee('Next required action')
        ->assertSee('Responsible office')
        ->assertSee('RFQs sent to at least 3 suppliers')
        ->assertSee('No posting required')
        ->assertSee('Audit history')
        ->assertDontSee('Pre-bid conference &amp; bid bulletins', false);

    ($this->dump)('staff-record-bidding', testCase()->actingAs($this->staff)->get(route('staff.procurement.show', $this->road)))
        ->assertOk()
        ->assertSee('Competitive Bidding')
        ->assertSee('Pre-bid conference &amp; bid bulletins', false);
});

it('shows bidders the fee, bid security, submission method, deadline and their status', function () {
    ($this->dump)('bidder-dashboard', testCase()->actingAs($this->bidder)->get(route('bidder.dashboard')))
        ->assertOk()
        ->assertSee('Bidding overview')
        ->assertSee('SJ-BAC-2026-I-004')
        ->assertSee('Documents fee ₱5,000.00')
        ->assertSee('Submitted');

    ($this->dump)('bidder-opportunity', testCase()->actingAs($this->bidder)->get(route('bidder.opportunities.show', $this->road)))
        ->assertOk()
        ->assertSee('Bidding documents fee — ₱5,000.00')
        ->assertSee('Bid security — required')
        ->assertSee('Bid Securing Declaration or 2% of the ABC in cash.')
        ->assertSee('Online, through this portal')
        ->assertSee('Deadline for submission of bids')
        ->assertSee('Not submitted');

    testCase()->actingAs($this->bidder)->get(route('bidder.opportunities.show', $this->rfq))
        ->assertOk()
        ->assertSee('none for this notice')
        ->assertSee('Deadline for submission of quotations')
        ->assertSee('RCPT-0042');
});

it('follows a purchase request on the office dashboard and guides the admin through recording one', function () {
    ($this->dump)('enduser-dashboard', testCase()->actingAs($this->office)->get(route('end-user.dashboard')))
        ->assertOk()
        ->assertSee('PR-2026-0031')
        ->assertDontSee('New purchase request');

    ($this->dump)('enduser-request-form', testCase()->actingAs($this->admin)->get(route('admin.requests.create')))
        ->assertOk()
        ->assertSee('data-stepped', false)
        ->assertSee('Review and submit')
        ->assertSee('Planning documents');
});

it('leads the overview with the work waiting for each role, and leaves out what is not', function () {
    User::create(['name' => 'New Supplier', 'email' => 'portal-new-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'pending', 'company' => 'New Supplier Co.']);

    testCase()->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
        ->assertSeeInOrder(['Needs your action', 'Purchase requests to review', 'Bidder registrations', 'Filter by stage'])
        ->assertSee(route('admin.users', ['filter' => 'pending']), false)
        ->assertDontSee('Notices to Proceed to issue')
        ->assertDontSee('Past the IRR award period');

    // The Secretariat reviews requests; bidder registrations stay with the BAC admin.
    testCase()->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()
        ->assertSee('Purchase requests to review')
        ->assertDontSee('Bidder registrations');

    ProcurementRequest::query()->delete();
    User::where('status', 'pending')->delete();
    testCase()->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()
        ->assertDontSee('Purchase requests to review');
});

it('sends the old procurement list URLs to the Projects page', function () {
    testCase()->actingAs($this->admin)->get('/procurements')->assertRedirect('/admin/projects');
    testCase()->actingAs($this->admin)->get('/procurements/publish')->assertRedirect('/admin/projects?status=open');
});

it('welcomes the bidder on the first dashboard visit after signing in, once', function () {
    testCase()->actingAs($this->bidder)->withSession(['bidder_welcome' => true])
        ->get(route('bidder.dashboard'))->assertOk()
        ->assertSee('Welcome back, Mindoro Builders Corp.!')
        ->assertSee('data-bidder-welcome', false);

    testCase()->actingAs($this->bidder)->get(route('bidder.dashboard'))->assertOk()
        ->assertDontSee('data-bidder-welcome', false);
});
