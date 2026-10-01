<?php

use App\Models\Bid;
use App\Models\Project;
use App\Models\ProjectProceeding;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\ProcurementLifecycle;
use App\Support\ProcurementMode;
use App\Support\ProcurementPipeline;
use App\Support\ProcurementTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'modes-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);

    $this->project = function (array $attributes = [], array $schedule = []): Project {
        $deadline = $attributes['deadline'] ?? workdayAt(20, 10, 0);
        $project = Project::create(array_merge([
            'title' => 'Supply of office equipment',
            'description' => 'Office equipment for the Municipal Treasurer.',
            'category' => 'goods',
            'procurement_mode' => 'small_value_procurement',
            'legal_basis' => 'ra_12009',
            'budget' => 350000,
            'deadline' => $deadline,
            'status' => 'draft',
            'philgeps_reference_no' => '11410616',
            'end_user_unit' => 'Municipal Treasurer\'s Office',
        ], $attributes));
        ProjectSchedule::create(array_merge([
            'project_id' => $project->id,
            'date_posted' => now()->toDateString(),
            'bid_submission_deadline' => $deadline,
        ], $schedule));

        return $project->fresh(['schedule']);
    };
});

it('applies the 2025 IRR of RA 12009 to new projects and RA 9184 rules to older records', function () {
    $new = ($this->project)(['procurement_mode' => 'public_bidding', 'budget' => 2000000]);
    $old = ($this->project)(['procurement_mode' => 'public_bidding', 'budget' => 2000000, 'legal_basis' => null]);

    // Pre-bid conference: required from ₱3M under RA 12009 (Sec. 51.1), ₱1M under RA 9184 (Sec. 22.1).
    expect($new->mode()->requiresPrebid())->toBeFalse()
        ->and($new->mode()->prebidThreshold())->toBe(3000000.0)
        ->and($old->mode()->requiresPrebid())->toBeTrue()
        ->and($old->mode()->legalBasisLabel())->toContain('RA 9184');

    // Award within 60 calendar days of the bid opening under RA 12009 (Sec. 67.1).
    $opening = now()->subDays(61);
    expect($new->mode()->awardDueDate($opening)->isPast())->toBeTrue()
        ->and($old->mode()->awardDueDate($opening)->isPast())->toBeFalse();
});

it('uses separate stages for competitive bidding and RFQ-based small value procurement', function () {
    $bidding = ProcurementTimeline::forProject(($this->project)(['procurement_mode' => 'public_bidding', 'budget' => 4000000]));
    $svp = ProcurementTimeline::forProject(($this->project)());

    $biddingKeys = collect($bidding->stages())->pluck('key');
    $svpKeys = collect($svp->stages())->pluck('key');

    expect($biddingKeys)->toContain('prebid', 'opening')->not->toContain('rfq_sent')
        ->and($svpKeys)->toContain('rfq_sent', 'submission', 'evaluation')->not->toContain('prebid', 'opening')
        ->and(collect($svp->stages())->firstWhere('key', 'evaluation')['label'])->toBe('Abstract of Quotations & evaluation')
        ->and(collect($svp->keyDates())->pluck('label'))->not->toContain('Bid opening');
});

it('posts an SVP RFQ only above ₱200,000 and caps SVP at the LGU ceiling', function () {
    config()->set('bac-office.lgu', ['type' => 'municipality', 'income_class' => 1]);

    $small = ($this->project)(['budget' => 150000, 'philgeps_reference_no' => null]);
    $posted = ($this->project)(['budget' => 350000, 'philgeps_reference_no' => null]);
    $tooLarge = ($this->project)(['budget' => 450000]);

    expect($small->mode()->requiresPosting())->toBeFalse()
        ->and($small->publicationBlockers())->not->toHaveKey('philgeps_reference_no')
        ->and($posted->mode()->minimumPostingDays())->toBe(3)
        ->and($posted->publicationBlockers())->not->toHaveKey('philgeps_reference_no')
        ->and(ProcurementMode::lguSvpCeiling())->toBe(400000.0)
        ->and($tooLarge->publicationBlockers()['budget'])->toContain('Small Value Procurement ceiling');

    // An RFQ does not need a bid opening schedule to be posted.
    expect($small->publicationBlockers())->not->toHaveKey('bid_opening_date');
});

it('rejects Shopping under RA 12009 and requires a ground for Negotiated Procurement', function () {
    $shopping = ($this->project)(['procurement_mode' => 'shopping']);
    $negotiated = ($this->project)(['procurement_mode' => 'negotiated_procurement', 'negotiation_ground' => null]);
    $afterFailures = ($this->project)(['procurement_mode' => 'negotiated_procurement', 'negotiation_ground' => 'two_failed_biddings']);
    $emergency = ($this->project)(['procurement_mode' => 'negotiated_procurement', 'negotiation_ground' => 'emergency']);

    expect($shopping->publicationBlockers())->toHaveKey('procurement_mode')
        ->and($negotiated->publicationBlockers())->toHaveKey('negotiation_ground')
        ->and($afterFailures->mode()->requiresPosting())->toBeTrue()
        ->and($emergency->mode()->requiresPosting())->toBeFalse();
});

it('records RFQs sent to at least three suppliers and an abstract only after a quotation is received', function () {
    $project = ($this->project)(['status' => 'open', 'deadline' => now()->subHour()], ['bid_submission_deadline' => now()->subHour()]);
    $lifecycle = app(ProcurementLifecycle::class);

    expect(fn () => $lifecycle->recordProceeding($project, $this->admin, ['type' => ProjectProceeding::TYPE_RFQ_ISSUED, 'occurred_at' => now()->subDays(2), 'recipients_count' => 2], null))
        ->toThrow(ValidationException::class);

    $lifecycle->recordProceeding($project, $this->admin, ['type' => ProjectProceeding::TYPE_RFQ_ISSUED, 'occurred_at' => now()->subDays(2)->toDateTimeString(), 'recipients_count' => 3], null);

    // Competitive-bidding records are not part of an RFQ.
    expect(fn () => $lifecycle->recordProceeding($project, $this->admin, ['type' => ProjectProceeding::TYPE_BID_BULLETIN, 'occurred_at' => now()->subDays(2)->toDateTimeString(), 'reference_no' => 'BB-1'], null))
        ->toThrow(ValidationException::class);

    // No quotation yet: the deadline must be extended, not the abstract recorded.
    expect(fn () => $lifecycle->recordProceeding($project, $this->admin, ['type' => ProjectProceeding::TYPE_ABSTRACT, 'occurred_at' => now()->toDateTimeString(), 'reference_no' => 'AOQ-1'], null))
        ->toThrow(ValidationException::class);

    $timeline = ProcurementTimeline::forProject($project->fresh());
    $stages = collect($timeline->stages())->keyBy('key');
    expect($stages['rfq_sent']['state'])->toBe('done')
        ->and($stages['submission']['note'])->toContain('No quotation received')
        ->and(collect($timeline->stages())->where('state', 'current')->count())->toBe(1);
});

it('enforces the pre-bid and bid bulletin periods for competitive bidding', function () {
    $deadline = now()->addDays(5)->setTime(10, 0);
    $project = ($this->project)(['procurement_mode' => 'public_bidding', 'status' => 'open', 'deadline' => $deadline], ['bid_submission_deadline' => $deadline]);
    $lifecycle = app(ProcurementLifecycle::class);

    expect(fn () => $lifecycle->recordProceeding($project, $this->admin, ['type' => ProjectProceeding::TYPE_PRE_BID, 'occurred_at' => now()->subHour()->toDateTimeString()], null))
        ->toThrow(ValidationException::class, 'at least 12 calendar days');

    expect(fn () => $lifecycle->recordProceeding($project, $this->admin, ['type' => ProjectProceeding::TYPE_BID_BULLETIN, 'occurred_at' => now()->subHour()->toDateTimeString(), 'reference_no' => 'SBB-1'], null))
        ->toThrow(ValidationException::class, 'at least 7 calendar days');
});

it('never awards automatically: the lowest bid still waits for evaluation, a BAC resolution and HoPE approval', function () {
    $project = ($this->project)(['procurement_mode' => 'public_bidding', 'budget' => 900000, 'status' => 'open', 'deadline' => now()->subDay(), 'bids_opened_at' => now()->subHours(20)], ['bid_submission_deadline' => now()->subDay()]);
    $bidder = User::create(['name' => 'Low Bidder', 'email' => 'low@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active']);
    Bid::create(['user_id' => $bidder->id, 'project_id' => $project->id, 'bid_amount' => 500000, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED, 'submitted_at' => now()->subDays(2), 'submission_channel' => 'electronic']);

    $timeline = ProcurementTimeline::forProject($project->fresh());
    $stages = collect($timeline->stages())->keyBy('key');

    expect($stages['award']['state'])->not->toBe('done')
        ->and($timeline->contractedBid())->toBeNull()
        ->and($project->fresh()->awards()->count())->toBe(0);
});

it('groups procurements into pipeline buckets and filters the register', function () {
    ($this->project)(['title' => 'Draft goods', 'status' => 'draft']);
    ($this->project)(['title' => 'Posted RFQ', 'status' => 'open', 'philgeps_posted_at' => now()->subDay()]);
    ($this->project)(['title' => 'Road bidding', 'procurement_mode' => 'public_bidding', 'budget' => 5000000, 'status' => 'open']);

    $pipeline = ProcurementPipeline::forAdmin();
    $counts = $pipeline->bucketCounts();

    expect($counts['preparation'])->toBe(1)
        ->and($counts['posted'])->toBe(2)
        // Stage counts follow the register's mode and search, like the stage links do.
        ->and($pipeline->bucketCounts(['mode' => 'competitive'])['posted'])->toBe(1)
        ->and($pipeline->bucketCounts(['mode' => 'competitive'])['preparation'])->toBe(0)
        ->and($pipeline->bucketCounts(['mode' => 'alternative'])['posted'])->toBe(1)
        ->and($pipeline->bucketCounts(['q' => 'road'])['posted'])->toBe(1)
        ->and($pipeline->filtered(['mode' => 'alternative', 'state' => 'active'])->pluck('title')->all())->toEqualCanonicalizing(['Draft goods', 'Posted RFQ'])
        ->and($pipeline->filtered(['q' => 'road', 'state' => 'active'])->pluck('title')->all())->toBe(['Road bidding'])
        ->and($pipeline->filtered(['flag' => 'posting', 'state' => 'active'])->pluck('title')->all())->toEqualCanonicalizing(['Draft goods', 'Road bidding']);

    testCase()->actingAs($this->admin)->get(route('admin.dashboard', ['stage' => 'posted']))
        ->assertOk()
        ->assertSee('Pipeline by stage')
        ->assertSee('Posted RFQ')
        ->assertSee('Road bidding')
        ->assertDontSee('Draft goods');
});
