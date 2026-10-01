<?php

use App\Models\Award;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidWorkflow;
use App\Support\ProcurementClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    config(['bac-office.demo_clock.enabled' => true, 'bac-office.demo_clock.allow_testing' => true]);
    Storage::fake('local');

    $makeUser = fn (string $email, string $role) => User::create([
        'name' => ucfirst($role).' Clock Test',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => $role,
        'status' => 'active',
        'company' => $role === 'bidder' ? 'Demo Supplier Ltd.' : null,
    ]);
    $this->admin = $makeUser('clock-admin@example.com', 'admin');
    $this->supplier = $makeUser('clock-supplier@example.com', 'bidder');
    $this->clock = app(ProcurementClock::class);
});

afterEach(function () {
    app(ProcurementClock::class)->reset();
});

it('lets only an admin set Manila demo time while keeping the demo banner removed', function () {
    testCase()->actingAs($this->admin)
        ->put(route('admin.demo-clock.update'), ['simulated_at' => '2026-10-05T10:00'])
        ->assertRedirect();

    expect($this->clock->now()->timezone('Asia/Manila')->format('Y-m-d H:i'))->toBe('2026-10-05 10:00');
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()->assertDontSee('DEMO MODE')->assertDontSee('Set demo date/time');
    testCase()->actingAs($this->supplier)->put(route('admin.demo-clock.update'), ['simulated_at' => '2026-09-29T09:00'])->assertForbidden();

    config(['app.env' => 'production']);
    expect($this->clock->demoModeEnabled())->toBeFalse();
    config(['app.env' => 'testing']);
});

it('moves Sep 29 to the Oct 5 opening and award date, then rewinds and restores stages and sealed access', function () {
    $this->clock->set(\Carbon\CarbonImmutable::parse('2026-09-29 09:00', 'Asia/Manila'), $this->admin->id);
    $project = Project::create([
        'title' => 'Demo laptop procurement',
        'description' => 'Clock rewind test project.',
        'reference_no' => 'SJOM-CLOCK-001',
        'category' => 'goods',
        'procurement_mode' => 'public_bidding',
        'legal_basis' => 'ra_12009',
        'budget' => 500000,
        'status' => 'open',
        'published_at' => '2026-09-29 08:00:00',
        'deadline' => '2026-10-05 09:00:00',
        'submission_mode' => Project::SUBMISSION_ELECTRONIC,
    ]);
    ProjectSchedule::create([
        'project_id' => $project->id,
        'date_posted' => '2026-09-29',
        'bid_submission_deadline' => '2026-10-05 09:00:00',
        'bid_opening_date' => '2026-10-05 10:00:00',
    ]);
    $scheduled = Project::create([
        'title' => 'Scheduled October procurement',
        'description' => 'Not public until October 5.',
        'reference_no' => 'SJOM-CLOCK-002',
        'category' => 'goods',
        'procurement_mode' => 'public_bidding',
        'legal_basis' => 'ra_12009',
        'budget' => 500000,
        'status' => 'open',
        'published_at' => '2026-10-05 08:00:00',
        'deadline' => '2026-10-20 09:00:00',
    ]);
    ProjectSchedule::create(['project_id' => $scheduled->id, 'date_posted' => '2026-10-05', 'bid_submission_deadline' => '2026-10-20 09:00:00', 'bid_opening_date' => '2026-10-20 10:00:00']);

    $bid = Bid::create([
        'project_id' => $project->id,
        'user_id' => $this->supplier->id,
        'bid_amount' => 490000,
        'status' => 'pending',
        'workflow_step' => Bid::STEP_SUBMITTED,
        'workflow_step_updated_at' => now(),
        'submission_channel' => Bid::CHANNEL_ELECTRONIC,
        'submitted_at' => now(),
        'receipt_no' => 'DEMO-RECEIPT-001',
    ]);
    Storage::disk('local')->put('bid-submissions/demo/technical.pdf', '%PDF-1.4 demo sealed file');
    $document = BidDocument::create([
        'bid_id' => $bid->id,
        'requirement_key' => 'technical',
        'component' => BidDocument::COMPONENT_TECHNICAL,
        'label' => 'Technical Proposal',
        'file_path' => 'bid-submissions/demo/technical.pdf',
        'original_name' => 'technical.pdf',
        'size' => 27,
    ]);

    expect($project->fresh()->isOpenForBidding())->toBeTrue()
        ->and($bid->fresh()->isSealed())->toBeTrue();
    testCase()->get(route('public.procurement'))->assertOk()->assertDontSee('Scheduled October procurement');
    testCase()->get(route('public.procurement.show', $scheduled))->assertNotFound();
    testCase()->get(route('public.procurement.show', $project))->assertOk();
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', ['bid' => $bid, 'bidDocument' => $document]))->assertForbidden();

    $this->clock->set(\Carbon\CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Manila'), $this->admin->id);
    expect($project->fresh()->isOpenForBidding())->toBeFalse();
    testCase()->get(route('public.procurement'))->assertOk()->assertSee('Scheduled October procurement');
    testCase()->get(route('public.procurement.show', $scheduled))->assertOk();
    app(BidWorkflow::class)->openBids($project->fresh(), $this->admin);
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', ['bid' => $bid, 'bidDocument' => $document]))->assertOk();
    expect($bid->fresh()->isSealed())->toBeFalse();

    $this->clock->set(\Carbon\CarbonImmutable::parse('2026-10-15 11:00', 'Asia/Manila'), $this->admin->id);
    $bid->refresh()->forceFill([
        'workflow_step' => Bid::STEP_NOTICE_TO_PROCEED,
        'workflow_step_updated_at' => now(),
        'notice_of_award_at' => now(),
        'notice_to_proceed_at' => now(),
        'notice_to_proceed_by' => $this->admin->id,
        'status' => 'awarded',
    ])->save();
    $award = Award::create([
        'project_id' => $project->id,
        'bid_id' => $bid->id,
        'bidder_id' => $this->supplier->id,
        'contract_amount' => 490000,
        'notice_of_award_date' => '2026-10-15',
        'contract_date' => '2026-10-15',
        'status' => Award::STATUS_VALID,
        'certificate_status' => Award::STATUS_VALID,
    ]);
    $project->forceFill(['status' => 'awarded'])->save();
    expect(Award::query()->publiclyPosted()->whereKey($award->id)->exists())->toBeTrue();
    config(['bac-office.demo_clock.enabled' => false]);
    \Illuminate\Support\Carbon::setTestNow();
    \Carbon\Carbon::setTestNow();
    expect(Award::query()->whereKey($award->id)->exists())->toBeFalse();
    config(['bac-office.demo_clock.enabled' => true]);
    expect($bid->fresh()->notice_to_proceed_at)->not->toBeNull();

    $this->clock->set(\Carbon\CarbonImmutable::parse('2026-09-29 09:00', 'Asia/Manila'), $this->admin->id);
    $rewoundProject = Project::query()->findOrFail($project->id);
    $rewoundBid = Bid::query()->findOrFail($bid->id);
    expect($rewoundProject->status)->toBe('open')
        ->and($rewoundProject->isOpenForBidding())->toBeTrue()
        ->and($rewoundProject->bidsAreOpened())->toBeFalse()
        ->and($scheduled->fresh()->published_at)->toBeNull()
        ->and($rewoundBid->workflow_step)->toBe(Bid::STEP_SUBMITTED)
        ->and($rewoundBid->notice_to_proceed_at)->toBeNull()
        ->and($rewoundBid->isSealed())->toBeTrue()
        ->and(Award::query()->publiclyPosted()->whereKey($award->id)->exists())->toBeFalse();
    testCase()->get(route('public.procurement'))->assertOk()->assertDontSee('Scheduled October procurement');
    testCase()->get(route('public.procurement.show', $scheduled))->assertNotFound();
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', ['bid' => $bid, 'bidDocument' => $document]))->assertForbidden();
    testCase()->get(route('certificate.verify', $award))->assertNotFound();

    $this->clock->set(\Carbon\CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Manila'), $this->admin->id);
    expect($project->fresh()->bidsAreOpened())->toBeTrue()
        ->and($bid->fresh()->isSealed())->toBeFalse()
        ->and(Award::query()->publiclyPosted()->whereKey($award->id)->exists())->toBeFalse();
    testCase()->actingAs($this->admin)->get(route('admin.bid.component-file', ['bid' => $bid, 'bidDocument' => $document]))->assertOk();

    $this->clock->set(\Carbon\CarbonImmutable::parse('2026-10-15 11:00', 'Asia/Manila'), $this->admin->id);
    expect(Award::query()->publiclyPosted()->whereKey($award->id)->exists())->toBeTrue();
    config(['bac-office.demo_clock.enabled' => false]);
    \Illuminate\Support\Carbon::setTestNow();
    \Carbon\Carbon::setTestNow();
    expect(Award::query()->whereKey($award->id)->exists())->toBeFalse();
    config(['bac-office.demo_clock.enabled' => true]);
    testCase()->get(route('public.awards'))->assertOk()->assertSee('Demo laptop procurement');
});