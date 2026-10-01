<?php

use App\Models\Award;
use App\Models\Bid;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
 * Public posting follows the server clock in Philippine time: an award dated
 * October 5 (or a project published for October 5) stays hidden on September
 * 29, including its search results, detail page, documents and direct URLs,
 * and appears from 12:00 AM on October 5. The BAC sees it as Scheduled.
 */
beforeEach(function () {
    testCase()->withoutVite();
    Storage::fake('local');
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');

    $this->at = fn (string $moment) => $this->travelTo(Carbon::parse($moment, 'Asia/Manila'));
    ($this->at)('2026-09-29 10:00');

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'schedule-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);

    // An award whose Notice of Award is dated October 5, with its QR document.
    $project = Project::create(['reference_no' => 'SJ-SCHED-01', 'title' => 'Supply of rice seeds', 'description' => 'Scheduled award.', 'budget' => 900000, 'status' => 'awarded', 'deadline' => Carbon::parse('2026-09-10', 'Asia/Manila')]);
    $bidder = User::create(['name' => 'Seed Traders', 'email' => 'seed@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Seed Traders']);
    $bid = Bid::create(['project_id' => $project->id, 'user_id' => $bidder->id, 'bid_amount' => 850000, 'status' => 'approved', 'submitted_at' => Carbon::parse('2026-09-09', 'Asia/Manila')]);
    Storage::disk('local')->put('awards/noa-sched.pdf', "%PDF-1.4\n%%EOF");
    $this->award = Award::create([
        'project_id' => $project->id, 'bid_id' => $bid->id, 'bidder_id' => $bidder->id, 'contract_amount' => 850000,
        'notice_of_award_date' => '2026-10-05', 'status' => Award::STATUS_VALID, 'certificate_status' => Award::STATUS_VALID,
        'certificate_file_path' => 'awards/noa-sched.pdf', 'qr_token' => Str::random(40), 'verification_token' => Str::random(40),
    ]);

    // A procurement published for October 5, 8:00 AM, and one whose schedule only has a date posted.
    $this->posting = function (string $title, ?string $publishedAt, ?string $datePosted): Project {
        $project = Project::create(['reference_no' => 'SJ-'.Str::upper(Str::random(5)), 'title' => $title, 'description' => 'Scheduled posting.', 'category' => 'goods', 'procurement_mode' => 'public_bidding', 'budget' => 500000, 'status' => 'open', 'deadline' => Carbon::parse('2026-10-30 10:00', 'Asia/Manila'), 'published_at' => $publishedAt ? Carbon::parse($publishedAt, 'Asia/Manila') : null]);
        ProjectSchedule::create(['project_id' => $project->id, 'date_posted' => $datePosted, 'bid_submission_deadline' => Carbon::parse('2026-10-30 10:00', 'Asia/Manila')]);
        $path = 'project-documents/project_doc_'.$project->id.'_20261005_0.pdf';
        Storage::disk('public')->put($path, "%PDF-1.4\n%%EOF");
        ProjectDocument::create(['project_id' => $project->id, 'original_name' => 'Invitation to Bid.pdf', 'file_path' => $path, 'document_type' => 'invitation_to_bid']);

        return $project->fresh();
    };
    $this->timed = ($this->posting)('Supply of hospital beds', '2026-10-05 08:00', '2026-10-05');
    $this->dated = ($this->posting)('Supply of school chairs', null, '2026-10-05');
    $this->current = ($this->posting)('Supply of printer ink', '2026-09-20 09:00', '2026-09-20');
});

it('keeps an award dated October 5 off every public page and URL on September 29, and posts it on October 5', function () {
    $publicUrls = fn () => [
        route('public.awards.document', $this->award->qr_token),
        route('public.certificate.view', $this->award->qr_token),
        route('certificate.verify', $this->award),
    ];

    // September 29: not listed, not found by search, not on the homepage, no direct access.
    testCase()->get(route('public.awards'))->assertOk()->assertDontSee('Supply of rice seeds')->assertDontSee('October 05, 2026');
    testCase()->get(route('public.awards', ['q' => 'rice seeds']))->assertOk()->assertDontSee('Supply of rice seeds');
    testCase()->get(route('home'))->assertOk()->assertDontSee('Seed Traders');
    foreach ($publicUrls() as $url) {
        testCase()->get($url)->assertNotFound();
    }
    expect($this->award->fresh()->isScheduledForPublication())->toBeTrue();

    // The BAC still sees it, marked Scheduled.
    testCase()->actingAs($this->admin)->get(route('admin.awards.index'))->assertOk()
        ->assertSee('Supply of rice seeds')
        ->assertSee('Scheduled: public on Oct 05, 2026 12:00 AM');
    auth()->logout();

    // One minute before midnight, still hidden.
    ($this->at)('2026-10-04 23:59');
    testCase()->get(route('public.awards'))->assertDontSee('Supply of rice seeds');
    testCase()->get(route('public.awards.document', $this->award->qr_token))->assertNotFound();

    // 12:00 AM October 5 (Philippine time): posted, and the posted date is not in the future.
    ($this->at)('2026-10-05 00:00');
    testCase()->get(route('public.awards'))->assertOk()->assertSee('Supply of rice seeds')->assertSee('October 05, 2026');
    foreach ($publicUrls() as $url) {
        testCase()->get($url)->assertOk();
    }

    ($this->at)('2026-10-10 09:00');
    testCase()->get(route('public.awards', ['q' => 'rice seeds']))->assertOk()->assertSee('Supply of rice seeds');
    expect($this->award->fresh()->isScheduledForPublication())->toBeFalse();
});

it('keeps a procurement scheduled for October 5 off the public listing, detail page and documents until then', function () {
    $direct = fn (Project $project) => [
        route('public.procurement.show', $project),
        route('public.procurement.qr', $project),
        route('public.procurement.document.pdf', ['project' => $project, 'document' => 0]),
    ];

    // September 29: only the procurement already published is public.
    testCase()->get(route('public.procurement'))->assertOk()
        ->assertSee('Supply of printer ink')
        ->assertDontSee('Supply of hospital beds')
        ->assertDontSee('Supply of school chairs');
    testCase()->get(route('public.procurement', ['q' => 'hospital beds']))->assertDontSee('Supply of hospital beds');
    foreach ([$this->timed, $this->dated] as $project) {
        foreach ($direct($project) as $url) {
            testCase()->get($url)->assertNotFound();
        }
        expect($project->isOpenForBidding())->toBeFalse();
    }
    expect(Project::openForBidding()->pluck('title')->all())->toBe(['Supply of printer ink']);
    foreach ($direct($this->current) as $url) {
        testCase()->get($url)->assertOk();
    }

    // The BAC sees both as Scheduled.
    testCase()->actingAs($this->admin)->get(route('admin.projects'))->assertOk()
        ->assertSee('Scheduled: public Oct 05, 2026 8:00 AM')
        ->assertSee('Scheduled: public Oct 05, 2026 12:00 AM');
    auth()->logout();

    // 12:00 AM October 5: a date without a time is posted at midnight; the 8:00 AM posting is not yet.
    ($this->at)('2026-10-05 00:00');
    testCase()->get(route('public.procurement'))->assertSee('Supply of school chairs')->assertDontSee('Supply of hospital beds');
    testCase()->get(route('public.procurement.show', $this->timed))->assertNotFound();
    testCase()->get(route('public.procurement.show', $this->dated))->assertOk();

    // October 10: both are public, with their documents.
    ($this->at)('2026-10-10 09:00');
    testCase()->get(route('public.procurement'))->assertSee('Supply of hospital beds')->assertSee('Supply of school chairs');
    foreach ([$this->timed, $this->dated] as $project) {
        foreach ($direct($project) as $url) {
            testCase()->get($url)->assertOk();
        }
    }
    expect($this->timed->fresh()->isOpenForBidding())->toBeTrue();
});
