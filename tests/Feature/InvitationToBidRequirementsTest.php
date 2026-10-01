<?php

use App\Models\Bidder;
use App\Models\Project;
use App\Models\ProjectProceeding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Invitation to Bid contents and posting/transparency records required by the
 * RA 12009 IRR: Sec. 49.1 (pre-procurement conference), 50.2 (contents),
 * 50.3.1 (posting), 50.3.3 (electronic bids), 43.1 (observers), 38 (video).
 */
beforeEach(function () {
    testCase()->withoutVite();
    config()->set('bac-office.contact.person', 'Juan Dela Cruz');
    config()->set('bac-office.contact.phone', '(043) 491-1234');

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'itb-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);

    // A draft that satisfies everything except what each test leaves out.
    $this->draft = function (array $attributes = []): Project {
        $deadline = workdayAt(20, 10, 0);
        $project = Project::create($attributes + [
            'title' => 'Supply of office equipment', 'description' => 'Desktop computers and printers.',
            'category' => 'goods', 'location' => 'San Jose', 'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009',
            'budget' => 2500000, 'status' => 'draft', 'deadline' => $deadline, 'source_of_fund' => 'General Fund', 'contract_duration' => '30 calendar days',
            'submission_mode' => Project::SUBMISSION_MANUAL, 'submission_venue' => 'BAC Secretariat',
            'award_criterion' => 'lowest_calculated_bid', 'bid_opening_venue' => 'BAC Conference Room, Municipal Hall',
        ]);
        // Pre-bid conference: required from ₱3M, 12 days before the deadline and 7 after posting.
        $project->schedule()->create([
            'bid_submission_deadline' => $deadline, 'bid_opening_date' => $deadline->copy()->setTime(10, 30),
            'pre_bid_conference_date' => workdayAt(7, 10, 0),
        ]);
        $project->documents()->create(['original_name' => 'itb.pdf', 'file_path' => 'project-documents/itb.pdf', 'document_type' => 'invitation_to_bid']);

        return $project->fresh();
    };
    $this->blockers = fn (Project $project) => $project->fresh()->publicationBlockers(null, now());
});

it('requires the award criterion, the place of opening and the IT certification for electronic bids', function () {
    expect(($this->blockers)(($this->draft)()))->toBe([]);

    $missing = ($this->blockers)(($this->draft)(['award_criterion' => null, 'bid_opening_venue' => null]));
    expect($missing)->toHaveKeys(['award_criterion', 'bid_opening_venue'])
        ->and($missing['award_criterion'])->toContain('50.2(d)');

    // Electronic submission needs the IT certification (Sec. 50.3.3).
    $electronic = ($this->draft)(['submission_mode' => Project::SUBMISSION_ELECTRONIC]);
    expect(($this->blockers)($electronic))->toHaveKey('electronic_submission_authority');
    $electronic->update(['electronic_submission_authority' => 'MIS Certification No. 2026-03']);
    expect(($this->blockers)($electronic))->not->toHaveKey('electronic_submission_authority');

    // RA 12009 goods cannot use the consulting criterion; RA 9184 goods only LCRB.
    expect(($this->blockers)(($this->draft)(['award_criterion' => 'quality_based'])))->toHaveKey('award_criterion')
        ->and(($this->blockers)(($this->draft)(['legal_basis' => 'ra_9184', 'award_criterion' => 'mearb'])))->toHaveKey('award_criterion');
});

it('requires MEARB/MARB criteria weights totalling 100%, the MEARB ratio and the consulting procedure', function () {
    $mearb = ($this->draft)(['award_criterion' => 'mearb', 'evaluation_criteria' => [['name' => 'Technical', 'weight' => 60], ['name' => 'Experience', 'weight' => 30]]]);
    expect(($this->blockers)($mearb))->toHaveKeys(['evaluation_criteria', 'quality_price_ratio']);
    $mearb->update(['evaluation_criteria' => [['name' => 'Technical', 'weight' => 60], ['name' => 'Experience', 'weight' => 40]], 'quality_price_ratio' => 70]);
    expect(($this->blockers)($mearb))->toBe([]);

    $marb = ($this->draft)(['award_criterion' => 'marb', 'evaluation_criteria' => [['name' => 'Quality', 'weight' => 100]]]);
    expect(($this->blockers)($marb))->toBe([]);

    $consulting = ($this->draft)(['category' => 'consultancy', 'budget' => 1500000, 'award_criterion' => 'quality_based']);
    expect(($this->blockers)($consulting))->toHaveKey('evaluation_procedure');
    $consulting->update(['evaluation_procedure' => 'qcbe']);
    expect(($this->blockers)($consulting))->toBe([]);
});

it('requires a pre-procurement conference before publishing above the ABC threshold, recorded while still a draft', function () {
    $small = ($this->draft)(['budget' => 5000000]);
    expect($small->preProcurementConferenceRequired())->toBeFalse();

    $large = ($this->draft)(['budget' => 6500000]);
    expect(($this->blockers)($large))->toHaveKey('pre_procurement_conference');

    // Other proceedings wait for publication; the pre-procurement conference does not.
    testCase()->actingAs($this->admin)->post(route('admin.procurement.proceedings', $large), [
        'type' => ProjectProceeding::TYPE_POSTING_CERTIFICATE, 'occurred_at' => now()->subDay()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('type');
    testCase()->actingAs($this->admin)->post(route('admin.procurement.proceedings', $large), [
        'type' => ProjectProceeding::TYPE_PRE_PROCUREMENT, 'occurred_at' => now()->subDay()->setTime(10, 0)->format('Y-m-d\TH:i'),
        'reference_no' => 'Minutes 2026-015',
    ])->assertSessionHasNoErrors();

    expect(($this->blockers)($large))->toBe([]);

    // Once published, it can no longer be added.
    testCase()->actingAs($this->admin)->postJson(route('admin.project.publish', $large))->assertOk();
    testCase()->actingAs($this->admin)->post(route('admin.procurement.proceedings', $large), [
        'type' => ProjectProceeding::TYPE_PRE_PROCUREMENT, 'occurred_at' => now()->subDay()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('type');
});

it('records observers (COA + 2) and the 7-day posting certificate, and lists what is still to do', function () {
    $project = ($this->draft)(['budget' => 12000000]);
    $project->proceedings()->create(['type' => ProjectProceeding::TYPE_PRE_PROCUREMENT, 'title' => 'Pre-procurement conference', 'occurred_at' => now()->subDays(12), 'recorded_by' => $this->admin->id]);
    testCase()->actingAs($this->admin)->postJson(route('admin.project.publish', $project))->assertOk();
    $project->refresh()->forceFill(['published_at' => now()->subDays(9)])->save();

    $record = fn (array $data) => testCase()->actingAs($this->admin)->post(route('admin.procurement.proceedings', $project), $data);
    $record(['type' => ProjectProceeding::TYPE_OBSERVERS, 'occurred_at' => now()->subDays(3)->format('Y-m-d\TH:i'), 'recipients_count' => 2])->assertSessionHasErrors('recipients_count');
    $record(['type' => ProjectProceeding::TYPE_OBSERVERS, 'occurred_at' => now()->subDays(3)->format('Y-m-d\TH:i'), 'recipients_count' => 3])->assertSessionHasNoErrors();
    $record(['type' => ProjectProceeding::TYPE_POSTING_CERTIFICATE, 'occurred_at' => now()->subDays(5)->format('Y-m-d\TH:i')])->assertSessionHasErrors('occurred_at');
    $record(['type' => ProjectProceeding::TYPE_POSTING_CERTIFICATE, 'occurred_at' => now()->subDay()->format('Y-m-d\TH:i'), 'reference_no' => 'Cert. 2026-44'])->assertSessionHasNoErrors();

    // Goods above ₱10M: video recording / livestream is required and still to do.
    $items = collect(\App\Support\PostingCompliance::for($project->fresh()->load('proceedings')))->keyBy('key');
    expect($items['pre_procurement']['status'])->toBe('done')
        ->and($items['observers']['status'])->toBe('done')
        ->and($items['conspicuous']['status'])->toBe('done')
        ->and($items['video']['status'])->toBe('pending')
        ->and($items['philgeps']['status'])->toBe('pending');

    testCase()->actingAs($this->admin)->get(route('admin.procurement.show', $project))->assertOk()
        ->assertSee('Posting and transparency')->assertSee('Video recording and livestream')->assertSee('IRR Sec. 43.1');
});

it('shows the Invitation to Bid details and the BAC contact to bidders and the public', function () {
    $project = ($this->draft)(['award_criterion' => 'mearb', 'evaluation_criteria' => [['name' => 'Technical merit', 'weight' => 70], ['name' => 'Delivery', 'weight' => 30]], 'quality_price_ratio' => 60]);
    testCase()->actingAs($this->admin)->postJson(route('admin.project.publish', $project))->assertOk();

    $bidder = User::create(['name' => 'Mindoro Builders', 'email' => 'itb-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active']);
    Bidder::create(['user_id' => $bidder->id, 'company_name' => 'Mindoro Builders', 'contact_person' => 'R. Santos', 'contact_number' => '09170000000', 'business_address' => 'San Jose', 'approval_status' => 'approved', 'approved_at' => now()]);

    foreach ([testCase()->actingAs($bidder)->get(route('bidder.opportunities.show', $project)), testCase()->get(route('public.procurement.show', $project))] as $response) {
        $response->assertOk()
            ->assertSee('Most Economically Advantageous Responsive Bid (MEARB)')
            ->assertSee('Technical merit')
            ->assertSee('60% technical / 40% price')
            ->assertSee('BAC Conference Room, Municipal Hall')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('(043) 491-1234')
            ->assertSee('bacoffice@sanjose.gov.ph');
    }
});
