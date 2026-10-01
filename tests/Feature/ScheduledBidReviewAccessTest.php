<?php

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

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');
    $this->admin = User::create([
        'name' => 'BAC Chair', 'email' => 'scheduled-admin@example.com',
        'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active',
    ]);
    $this->project = function (string $ref, $opensAt): Project {
        $project = Project::create([
            'reference_no' => $ref, 'title' => "Road project {$ref}", 'description' => 'Review test.',
            'budget' => 1000000, 'status' => 'open', 'deadline' => now()->subHours(2),
            'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009',
            'submission_mode' => Project::SUBMISSION_ELECTRONIC,
            'award_criterion' => 'lowest_calculated_bid',
            'opening_documents_reference' => 'Bidding Documents, ITB 24',
        ]);
        ProjectSchedule::create([
            'project_id' => $project->id, 'bid_submission_deadline' => now()->subHours(2),
            'bid_opening_date' => $opensAt,
        ]);
        return $project->fresh(['schedule']);
    };
    $this->bid = function (Project $project, string $name, float $amount): Bid {
        $bidder = User::create([
            'name' => $name, 'email' => str($name)->slug().'@example.com',
            'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => $name,
        ]);
        $bid = Bid::create([
            'project_id' => $project->id, 'user_id' => $bidder->id, 'bid_amount' => $amount,
            'proposal_file' => "bids/{$bidder->id}-proposal.pdf", 'status' => 'pending',
            'workflow_step' => Bid::STEP_SUBMITTED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC,
            'submitted_at' => now()->subHours(3),
            'financial_opening_password_hash' => Hash::make('Opening-password-2026'),
        ]);
        foreach (['technical', 'financial'] as $component) {
            $path = "bids/{$bidder->id}-{$component}.pdf";
            Storage::disk('public')->put($path, "%PDF-1.4\n%%EOF");
            BidDocument::create([
                'bid_id' => $bid->id, 'requirement_key' => "{$component}_component",
                'component' => $component, 'label' => ucfirst($component), 'file_path' => $path,
                'original_name' => ucfirst($component)."-{$bidder->id}.pdf", 'size' => 14,
            ]);
        }
        Storage::disk('public')->put($bid->proposal_file, "%PDF-1.4\n%%EOF");
        return $bid->fresh();
    };
    $this->doc = fn (Bid $bid, string $part) => $bid->documents()->where('component', $part)->firstOrFail();
});

it('opens technical access at the scheduled Manila time and records automatic opening once', function () {
    $opensAt = now('Asia/Manila')->addMinutes(5)->startOfMinute();
    $project = ($this->project)('AUTO-OPEN', $opensAt);
    $bid = ($this->bid)($project, 'Scheduled Builder', 987654.32);
    $technical = ($this->doc)($bid, 'technical');

    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', [$bid, $technical]))->assertForbidden();
    $closedModal = testCase()->actingAs($this->admin)->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('admin.bid.view', $bid))->assertOk()->getContent();
    expect($closedModal)->toContain('data-technical-open="0"')->not->toContain('Technical-');

    $this->travelTo($opensAt->copy()->addSecond());
    testCase()->artisan('bids:open-scheduled-technical')->assertExitCode(0);
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', [$bid, $technical]))->assertOk();
    $project->refresh();
    expect($project->bids_opened_at)->not->toBeNull()->and($project->bids_opened_by)->toBeNull()
        ->and(AuditLog::where('action', 'bid_technical_documents_auto_opened')->count())->toBe(1);

    testCase()->actingAs($this->admin)->get(route('admin.bid.view', $bid), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', [$bid, $technical]))->assertOk();
    expect(AuditLog::where('action', 'bid_technical_documents_auto_opened')->count())->toBe(1);
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', [$bid, ($this->doc)($bid, 'financial')]))->assertForbidden();
    testCase()->actingAs($this->admin)->get(route('admin.bid.document.pdf', [$bid, 'proposal']))->assertForbidden();
    $openModal = testCase()->actingAs($this->admin)->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('admin.bid.view', $bid))->assertOk()->getContent();
    expect($openModal)->toContain('data-technical-open="1"')->toContain($technical->original_name)
        ->not->toContain('Financial-')->not->toContain('987,654.32');
});

it('blocks pending and rejected technical reviews and requires the correct bidder password for financial access', function () {
    $project = ($this->project)('FINANCIAL-GATE', now('Asia/Manila')->subMinute());
    $pending = ($this->bid)($project, 'Pending Builder', 750000.00);
    $rejected = ($this->bid)($project, 'Rejected Builder', 680000.00);

    testCase()->actingAs($this->admin)->get(route('admin.bid.view', $pending), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
    $pendingModal = testCase()->actingAs($this->admin)->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('admin.bid.view', $pending))->assertOk()->getContent();
    expect($pendingModal)->not->toContain('Financial Password')->not->toContain('Rejected Builder-')
        ->not->toContain('750,000.00');
    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.component-file', [$pending, ($this->doc)($pending, 'financial')]))->assertForbidden();
    testCase()->actingAs($this->admin)->get(route('admin.bid.document.pdf', [$pending, 'proposal']))->assertForbidden();

    $rejected->forceFill([
        'disqualified_at' => now(), 'disqualified_by' => $this->admin->id,
        'status' => 'rejected', 'workflow_step' => Bid::STEP_DISQUALIFIED,
    ])->save();
    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.component-file', [$rejected, ($this->doc)($rejected, 'financial')]))->assertForbidden();

    $pending->forceFill([
        'documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id,
        'workflow_step' => Bid::STEP_DOCUMENTS_VALIDATED,
    ])->save();
    $approvedModal = testCase()->actingAs($this->admin)->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->get(route('admin.bid.view', $pending))->assertOk()->getContent();
    expect($approvedModal)->toContain('Financial Password')->toContain('data-br-password-toggle')
        ->not->toContain($pending->financial_opening_password_hash)
        ->not->toContain('Verify the bidder-provided password')
        ->not->toContain('data-confirm=');

    testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $pending), [
        'opening_password' => 'Incorrect-password-2026',
    ])->assertSessionHasErrors('opening');
    expect($pending->fresh()->financial_opened_at)->toBeNull();
    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.component-file', [$pending, ($this->doc)($pending, 'financial')]))->assertForbidden();
    testCase()->actingAs($this->admin)->get(route('admin.bid.document.pdf', [$pending, 'proposal']))->assertForbidden();

    testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $pending), [
        'opening_password' => 'Opening-password-2026',
    ])->assertSessionHasNoErrors();
    expect($pending->fresh()->financial_opened_at)->not->toBeNull();
    testCase()->actingAs($this->admin)
        ->get(route('admin.bid.component-file', [$pending, ($this->doc)($pending, 'financial')]))->assertOk();
    testCase()->actingAs($this->admin)->get(route('admin.bid.document.pdf', [$pending, 'proposal']))->assertOk();
});