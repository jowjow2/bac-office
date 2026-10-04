<?php

use App\Models\Award;
use App\Models\Bid;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\QrSvg;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
 * The public QR images are drawn without PHP's XMLWriter (missing on Vercel,
 * where every QR answered 500), and the bidder QR leads to the bidder's record
 * of successful and approved bids.
 */
beforeEach(function () {
    testCase()->withoutVite();
    $this->bidderUser = User::create(['name' => 'Juan', 'email' => 'qr-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'PCIC Corporation']);
    $this->bidder = $this->bidderUser->bidderProfile()->create(['company_name' => 'PCIC Corporation', 'contact_person' => 'Juan', 'contact_number' => '09171234567', 'business_address' => 'San Jose', 'approval_status' => 'approved']);
    $this->project = function (string $ref, string $title) {
        $project = Project::create(['reference_no' => $ref, 'title' => $title, 'description' => 'x', 'budget' => 3450000, 'status' => 'awarded', 'deadline' => now()->subDays(20), 'published_at' => now()->subDays(40)]);
        ProjectSchedule::create(['project_id' => $project->id, 'bid_submission_deadline' => now()->subDays(20)]);

        return $project;
    };
});

it('draws the same QR matrix as the library, as plain SVG', function () {
    $url = 'https://bac-office.vercel.app/bidder/verify/abc123';
    $svg = QrSvg::render($url, 320);
    $matrix = Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix();

    $dark = 0;
    for ($y = 0; $y < $matrix->getWidth(); $y++) {
        for ($x = 0; $x < $matrix->getWidth(); $x++) {
            $dark += $matrix->get($x, $y) === 1 ? 1 : 0;
        }
    }

    expect($svg)->toStartWith('<?xml')->toContain('<svg')->toContain('width="320"')
        ->and(substr_count($svg, 'h1v1h-1z'))->toBe($dark);
});

it('serves the bidder, award and project QR images', function () {
    testCase()->get(route('public.bidder.qr', $this->bidder->fresh()->qr_token))->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');

    $project = ($this->project)('SJ-BAC-2026-I-001', 'Concreting of farm-to-market road');
    $bid = Bid::create(['project_id' => $project->id, 'user_id' => $this->bidderUser->id, 'bid_amount' => 3000000, 'status' => 'awarded', 'workflow_step' => Bid::STEP_AWARDED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subDays(21), 'receipt_no' => 'R-QR-1']);
    $award = Award::create(['project_id' => $project->id, 'bid_id' => $bid->id, 'bidder_id' => $this->bidderUser->id, 'contract_amount' => 3000000, 'notice_of_award_date' => now()->subDays(5)->toDateString(), 'status' => Award::STATUS_VALID, 'certificate_status' => Award::STATUS_VALID, 'qr_token' => Str::random(40), 'verification_token' => Str::random(40)]);
    testCase()->get(route('public.qr.show', $award->qr_token))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

    $open = Project::create(['reference_no' => 'SJ-BAC-2026-G-009', 'title' => 'Supply of laptops', 'description' => 'x', 'budget' => 300000, 'status' => 'open', 'deadline' => now()->addDays(9), 'published_at' => now()->subDay()]);
    testCase()->get(route('public.procurement.qr', $open))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
});

it('shows the bidder record with successful bids first, then bids approved for award', function () {
    $won = ($this->project)('SJ-BAC-2026-I-001', 'Concreting of farm-to-market road');
    $wonBid = Bid::create(['project_id' => $won->id, 'user_id' => $this->bidderUser->id, 'bid_amount' => 3000000, 'status' => 'awarded', 'workflow_step' => Bid::STEP_AWARDED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subDays(21), 'receipt_no' => 'R-QR-1']);
    Award::create(['project_id' => $won->id, 'bid_id' => $wonBid->id, 'bidder_id' => $this->bidderUser->id, 'contract_amount' => 3000000, 'notice_of_award_date' => now()->subDays(5)->toDateString(), 'status' => Award::STATUS_VALID, 'certificate_status' => Award::STATUS_VALID, 'qr_token' => Str::random(40), 'verification_token' => Str::random(40)]);

    $pending = ($this->project)('SJ-BAC-2026-G-002', 'Supply of office laptops');
    $pending->update(['status' => 'closed']);
    Bid::create(['project_id' => $pending->id, 'user_id' => $this->bidderUser->id, 'bid_amount' => 900000, 'status' => 'approved', 'workflow_step' => Bid::STEP_AWARDED, 'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subDays(21), 'receipt_no' => 'R-QR-2', 'award_decision' => Bid::AWARD_DECISION_APPROVED, 'award_decision_at' => now()->subDay()]);

    testCase()->get(route('public.bidder.verify', $this->bidder->fresh()->qr_token))->assertOk()
        ->assertSee('Verified by SJBAC')
        ->assertSeeInOrder(['Successful bids', 'Concreting of farm-to-market road', 'SJ-BAC-2026-I-001', '₱3,000,000.00', 'Approved for award', 'Supply of office laptops', 'Notice of Award pending'])
        // The price of a bid not yet awarded stays private.
        ->assertDontSee('900,000.00');
});
