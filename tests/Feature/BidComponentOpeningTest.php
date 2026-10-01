<?php

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\BidTracking;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Technical and financial components open only through recorded BAC Admin
 * opening events, never from the schedule, and each reveal is enforced in
 * the table, the review modal, file downloads and the CSV export.
 */
beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'opening-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'Secretariat', 'email' => 'opening-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);

    // Deadline and scheduled opening are already in the past.
    $this->makeProject = function (string $reference): Project {
        $project = Project::create([
            'reference_no' => $reference, 'title' => "Road concreting {$reference}", 'description' => 'Opening test.',
            'budget' => 1000000, 'status' => 'open', 'deadline' => now()->subHours(2),
            'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009', 'submission_mode' => Project::SUBMISSION_ELECTRONIC,
        ]);
        ProjectSchedule::create(['project_id' => $project->id, 'bid_submission_deadline' => now()->subHours(2), 'bid_opening_date' => now()->subHour()]);

        return $project;
    };

    $this->makeBid = function (Project $project, string $company, float $amount): Bid {
        $bidder = User::create(['name' => $company, 'email' => str($company)->slug().'@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => $company]);
        $bid = Bid::create([
            'project_id' => $project->id, 'user_id' => $bidder->id, 'bid_amount' => $amount,
            'proposal_file' => "bids/{$bidder->id}-price.pdf", 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED,
            'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subHours(3), 'receipt_no' => "R-{$bidder->id}", 'financial_opening_password_hash' => Hash::make('Opening-password-2026'),
        ]);
        foreach (['technical' => 'Technical-Envelope', 'financial' => 'Financial-Envelope'] as $component => $name) {
            $path = "bids/{$bidder->id}-{$component}.pdf";
            Storage::disk('public')->put($path, "%PDF-1.4\n%%EOF");
            BidDocument::create(['bid_id' => $bid->id, 'requirement_key' => "{$component}_component", 'component' => $component, 'label' => ucfirst($component), 'file_path' => $path, 'original_name' => "{$name}-{$bidder->id}.pdf", 'size' => 14]);
        }
        Storage::disk('public')->put($bid->proposal_file, "%PDF-1.4\n%%EOF");

        return $bid->fresh();
    };

    $this->openFinancial = fn (Bid $bid, string $password = 'Opening-password-2026') => testCase()->actingAs($this->admin)->post(route('admin.bid.open-financial', $bid), ['opening_password' => $password]);
    $this->file = fn (Bid $bid, string $component) => $bid->documents()->where('component', $component)->firstOrFail();
    $this->modal = fn (Bid $bid) => testCase()->actingAs($this->admin)->withHeader('X-Requested-With', 'XMLHttpRequest')->get(route('admin.bid.view', $bid))->assertOk()->getContent();
    $this->export = fn () => testCase()->actingAs($this->admin)->post(route('admin.bids.export'))->streamedContent();
    // A recorded, passed preliminary examination of the technical component.
    $this->passPreliminary = fn (Bid $bid) => $bid->forceFill(['documents_validated_at' => now(), 'documents_validated_by' => $this->admin->id])->save();
});

it('LCRB: the scheduled technical opening is automatic; financial access requires technical approval and the bidder password', function () {
    $project = ($this->makeProject)('LCRB-1');
    $bid = ($this->makeBid)($project, 'Mindoro Builders', 987654.32);
    $project->forceFill(['award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'Bidding Documents, ITB 24'])->save();

    expect($bid->isSealed())->toBeTrue()->and($bid->isFinancialSealed())->toBeTrue();
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', [$bid, ($this->file)($bid, 'technical')]))->assertOk();
    $project->refresh();
    expect($project->bids_opened_at)->not->toBeNull()->and($project->bids_opened_by)->toBeNull()
        ->and(AuditLog::where('action', 'bid_technical_documents_auto_opened')->count())->toBe(1);
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', [$bid, ($this->file)($bid, 'financial')]))->assertForbidden();
    testCase()->actingAs($this->admin)->get(route('admin.bid.document.pdf', [$bid, 'proposal']))->assertForbidden();
    expect(($this->modal)($bid))->toContain('Technical-Envelope')->not->toContain('Financial-Envelope')
        ->not->toContain('987,654.32')->not->toContain('Financial Password');

    ($this->passPreliminary)($bid);
    expect(($this->modal)($bid))->toContain('Financial Password')->not->toContain('Financial-Envelope');
    ($this->openFinancial)($bid, 'Incorrect-password-2026')->assertSessionHasErrors('opening');
    expect($bid->fresh()->isFinancialSealed())->toBeTrue();
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', [$bid, ($this->file)($bid, 'financial')]))->assertForbidden();
    ($this->openFinancial)($bid)->assertSessionHasNoErrors();
    $bid = $bid->fresh();
    expect($bid->isFinancialSealed())->toBeFalse()->and($bid->financial_opened_by)->toBe($this->admin->id);
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', [$bid, ($this->file)($bid, 'financial')]))->assertOk();
    testCase()->actingAs($this->admin)->get(route('admin.bid.document.pdf', [$bid, 'proposal']))->assertOk();
    ($this->openFinancial)($bid)->assertSessionHasErrors('opening');
    expect(BidTracking::where('bid_id', $bid->id)->where('stage', 'financial_opening')->count())->toBe(1);
});
it('MEARB: technical scoring remains recorded separately while one scheduled opening and bidder password gate finance', function () {
    $project = ($this->makeProject)('MEARB-1');
    $eligible = ($this->makeBid)($project, 'Occidental Engineering', 912000.00);
    $belowMinimum = ($this->makeBid)($project, 'Sablayan Works', 845000.00);

    testCase()->actingAs($this->admin)->post(route('admin.project.bid-opening-rules', $project), [
        'award_criterion' => 'mearb', 'opening_documents_reference' => 'Bidding Documents, Section III',
        'minimum_technical_score' => 70,
    ])->assertSessionHasNoErrors();

    testCase()->actingAs($this->admin)->post(route('admin.project.open-bids', $project))->assertSessionHasNoErrors();
    foreach ([$eligible, $belowMinimum] as $bid) ($this->passPreliminary)($bid);

    testCase()->actingAs($this->admin)->post(route('admin.bid.technical-score', $eligible), [
        'technical_score' => 82.5, 'technical_score_basis' => 'Rated per Section III criteria',
    ])->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.bid.technical-score', $belowMinimum), [
        'technical_score' => 60, 'technical_score_basis' => 'Rated per Section III criteria',
    ])->assertSessionHasNoErrors();
    testCase()->actingAs($this->admin)->post(route('admin.bid.technical-score', $eligible), [
        'technical_score' => 99, 'technical_score_basis' => 'Second attempt',
    ])->assertSessionHasErrors('opening');

    ($this->openFinancial)($eligible)->assertSessionHasNoErrors();
    ($this->openFinancial)($belowMinimum)->assertSessionHasNoErrors();
    expect($eligible->fresh()->isFinancialSealed())->toBeFalse()
        ->and($belowMinimum->fresh()->isFinancialSealed())->toBeFalse();
});
