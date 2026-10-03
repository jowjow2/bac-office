<?php

use App\Http\Middleware\ApplyTimePreview;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * An admin can preview the system as of a later date (e.g. after the
 * submission deadline): pages behave as of that time, nothing is saved,
 * and everyone else stays on real time.
 */
beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'preview-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);

    // Deadline in 9 days, opening 30 minutes later.
    $deadline = now()->addDays(9)->setTime(10, 0);
    $this->project = Project::create([
        'reference_no' => 'PREVIEW-1', 'title' => 'Preview road project', 'description' => 'Time preview test.',
        'budget' => 1000000, 'status' => 'open', 'deadline' => $deadline,
        'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009', 'submission_mode' => Project::SUBMISSION_ELECTRONIC,
    ]);
    ProjectSchedule::create(['project_id' => $this->project->id, 'bid_submission_deadline' => $deadline, 'bid_opening_date' => $deadline->copy()->addMinutes(30)]);

    $bidder = User::create(['name' => 'Mindoro Builders', 'email' => 'preview-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Builders']);
    $this->bid = Bid::create([
        'project_id' => $this->project->id, 'user_id' => $bidder->id, 'bid_amount' => 950000,
        'proposal_file' => "bids/{$bidder->id}-price.pdf", 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED,
        'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now(), 'receipt_no' => 'R-PREVIEW', 'financial_opening_password_hash' => Hash::make('Opening-password-2026'),
    ]);
    $path = "bids/{$bidder->id}-technical.pdf";
    Storage::disk('public')->put($path, "%PDF-1.4\n%%EOF");
    $this->technical = BidDocument::create(['bid_id' => $this->bid->id, 'requirement_key' => 'technical_component', 'component' => 'technical', 'label' => 'Technical', 'file_path' => $path, 'original_name' => 'Technical-Envelope.pdf', 'size' => 14]);

    $this->previewAt = $deadline->copy()->addMinutes(31)->format('Y-m-d\TH:i');
});

it('shows the system as of the preview time without saving anything', function () {
    $file = route('admin.bid.component-file', [$this->bid, $this->technical]);

    // Real time: before the deadline, the technical component is still sealed.
    testCase()->actingAs($this->admin)->get($file)->assertForbidden();

    testCase()->actingAs($this->admin)->put(route('admin.time-preview.update'), ['preview_at' => $this->previewAt])
        ->assertSessionHas(ApplyTimePreview::SESSION_KEY);

    // As of the preview: past the opening, so the technical component opens...
    testCase()->actingAs($this->admin)->get($file)->assertOk();

    // ...but the opening it recorded was rolled back.
    expect($this->project->fresh()->bids_opened_at)->toBeNull()
        ->and(AuditLog::where('action', 'bid_technical_documents_auto_opened')->count())->toBe(0);

    // The banner tells the admin that preview is on.
    testCase()->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
        ->assertSee('Time preview:')->assertSee('Back to real time');
});

it('refuses to save while the preview is on', function () {
    testCase()->actingAs($this->admin)->withSession([ApplyTimePreview::SESSION_KEY => now()->addDays(9)->format('Y-m-d H:i:s')]);

    testCase()->actingAs($this->admin)->from(route('admin.dashboard'))
        ->post(route('admin.bid.open-financial', $this->bid), ['opening_password' => 'Opening-password-2026'])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('error');
    expect($this->bid->fresh()->financial_opened_by)->toBeNull();

    testCase()->actingAs($this->admin)->postJson(route('admin.bid.open-financial', $this->bid), ['opening_password' => 'Opening-password-2026'])
        ->assertStatus(423)->assertJson(['time_preview' => true]);
});

it('goes back to real time', function () {
    testCase()->actingAs($this->admin)->put(route('admin.time-preview.update'), ['preview_at' => $this->previewAt]);
    testCase()->actingAs($this->admin)->delete(route('admin.time-preview.destroy'))
        ->assertSessionMissing(ApplyTimePreview::SESSION_KEY);

    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', [$this->bid, $this->technical]))->assertForbidden();
    testCase()->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Back to real time');
});

it('applies to admins only', function () {
    $bidder = User::where('role', 'bidder')->first();

    testCase()->actingAs($bidder)->put(route('admin.time-preview.update'), ['preview_at' => $this->previewAt]);
    expect(session(ApplyTimePreview::SESSION_KEY))->toBeNull();

    // A stray session value does nothing for a non-admin.
    testCase()->actingAs($bidder)->withSession([ApplyTimePreview::SESSION_KEY => now()->addDays(9)->format('Y-m-d H:i:s')])
        ->get(route('bidder.dashboard'))->assertDontSee('Back to real time');
});
