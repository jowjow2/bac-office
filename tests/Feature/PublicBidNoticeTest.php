<?php

use App\Models\Bid;
use App\Models\Bidder;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * "Login to Participate" / project QR always go through the login modal (or, for a
 * signed-in bidder, straight to the project), and the public Bid Notice Abstract
 * shows only what the Invitation to Bid publishes.
 */
beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');

    $this->project = Project::create([
        'reference_no' => 'SJ-BAC-2026-G-050', 'title' => 'Supply of laboratory reagents', 'description' => 'Reagents for the RHU laboratory.',
        'category' => 'goods', 'location' => 'Rural Health Unit I', 'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009',
        'budget' => 950000, 'status' => 'open', 'deadline' => now()->addDays(9), 'published_at' => now()->subDays(3),
        'contract_duration' => '30 calendar days', 'source_of_fund' => 'General Fund', 'end_user_unit' => 'Internal End-user Office',
        'opening_documents_reference' => 'INTERNAL-OPENING-RULE-REF', 'bid_opening_venue' => 'BAC Conference Room',
    ]);
    ProjectSchedule::create(['project_id' => $this->project->id, 'bid_submission_deadline' => now()->addDays(9), 'bid_opening_date' => now()->addDays(9)->addMinutes(30)]);

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'notice-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->bidder = User::create(['name' => 'Mindoro Lab Supply', 'email' => 'notice-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Lab Supply']);
    Bidder::create(['user_id' => $this->bidder->id, 'company_name' => 'Mindoro Lab Supply', 'contact_person' => 'R. Santos', 'contact_number' => '09170000000', 'business_address' => 'San Jose', 'approval_status' => 'approved', 'approved_at' => now()]);

    $this->participate = route('login.page', ['qr_project' => $this->project->id]);
});

it('sends a guest to the login modal with the project, then the bidder to that project after the email code', function () {
    testCase()->get($this->participate)
        ->assertRedirect(route('home'))
        ->assertSessionHas('auth_tab', 'login')
        ->assertSessionHas('scanned_project_title', 'Supply of laboratory reagents')
        ->assertSessionHas('participation_project_id', $this->project->id);

    testCase()->get(route('home'))->assertSee('Participating in: Supply of laboratory reagents');

    // Bidder login is completed with the emailed code; the redirect goes to the project.
    testCase()->withSession([
        'participation_project_id' => $this->project->id,
        'login_verification' => ['user_id' => $this->bidder->id, 'remember' => false, 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10)->timestamp],
    ])->postJson(route('login.verify-code'), ['code' => '123456'])
        ->assertOk()
        ->assertJson(['ok' => true, 'redirect' => route('bidder.opportunities.show', $this->project)]);

    testCase()->assertAuthenticatedAs($this->bidder);
    expect(session('participation_project_id'))->toBeNull();
});

it('does not jump a signed-in BAC admin to the dashboard: the login modal asks for a bidder account', function () {
    testCase()->actingAs($this->admin)->get($this->participate)
        ->assertRedirect(route('home'))
        ->assertSessionHas('scanned_project_signed_in_as', 'BAC Admin');

    // An admin who then signs in still lands on the admin dashboard.
    testCase()->withSession(['participation_project_id' => $this->project->id])
        ->postJson(route('login'), ['email' => 'notice-admin@example.com', 'password' => 'password'])
        ->assertOk()->assertJson(['redirect' => route('admin.dashboard')]);
});

it('takes a signed-in bidder straight to the project', function () {
    testCase()->actingAs($this->bidder)->get($this->participate)
        ->assertRedirect(route('bidder.opportunities.show', $this->project));

    // Without a scanned project, /login still goes to the dashboard as before.
    testCase()->actingAs($this->bidder)->get(route('login.page'))->assertRedirect(route('bidder.dashboard'));
});

it('publishes the bid notice without bid counts, internal references or non-public files', function () {
    Bid::create(['project_id' => $this->project->id, 'user_id' => $this->bidder->id, 'bid_amount' => 900000, 'status' => 'pending', 'submitted_at' => now()]);
    foreach (['itb.pdf' => 'invitation_to_bid', 'internal-memo.pdf' => 'other', 'specs.pdf' => 'technical_specifications'] as $name => $type) {
        $path = "project-documents/project_doc_{$this->project->id}_{$name}";
        Storage::disk('public')->put($path, "%PDF-1.4\n%%EOF");
        $this->project->documents()->create(['original_name' => $name, 'file_path' => $path, 'document_type' => $type]);
    }

    foreach ([route('public.procurement.show', $this->project), route('public.procurement')] as $url) {
        testCase()->get($url)->assertOk()
            ->assertSee('Bid Notice Abstract')
            ->assertSee('SJ-BAC-2026-G-050')
            ->assertSee('950,000.00')
            ->assertSee('Rural Health Unit I')
            ->assertSee('Invitation to Bid')
            ->assertSee('Technical Specifications')
            ->assertSee(route('login.page', ['qr_project' => $this->project->id]), false)
            ->assertDontSee('Bids Received')
            ->assertDontSee('internal-memo.pdf')
            ->assertDontSee('Internal End-user Office')
            ->assertDontSee('INTERNAL-OPENING-RULE-REF')
            ->assertDontSee('Mindoro Lab Supply')
            ->assertDontSee('900,000.00');
    }

    // Public file links index the public list only: the "Other" file is not reachable.
    $public = $this->project->fresh()->publicDocuments();
    expect($public->pluck('document_type')->all())->toBe(['invitation_to_bid', 'technical_specifications']);
    testCase()->get(route('public.procurement.document.pdf', ['project' => $this->project, 'document' => 2]))->assertNotFound();
});
