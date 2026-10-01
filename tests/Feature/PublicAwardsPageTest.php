<?php

use App\Models\Award;
use App\Models\Bid;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
 * Public /awards: the selected award's details and its published Notice of
 * Award, with the other records as selectable cards. Only real, published
 * files are shown; revoked awards are not posted.
 */
beforeEach(function () {
    testCase()->withoutVite();
    Storage::fake('local');

    $this->award = function (string $title, string $company, array $attributes = [], ?string $file = 'awards/noa.pdf'): Award {
        $project = Project::create(['reference_no' => 'SJ-'.Str::upper(Str::random(5)), 'title' => $title, 'description' => 'Award page test.', 'budget' => 1000000, 'status' => 'awarded', 'deadline' => now()->subDays(20)]);
        $bidder = User::create(['name' => $company, 'email' => Str::slug($company).'@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => $company]);
        $bid = Bid::create(['project_id' => $project->id, 'user_id' => $bidder->id, 'bid_amount' => 845000, 'status' => 'approved', 'submitted_at' => now()->subDays(19)]);
        if ($file) Storage::disk('local')->put($file, "%PDF-1.4\n%%EOF");

        return Award::create($attributes + [
            'project_id' => $project->id, 'bid_id' => $bid->id, 'bidder_id' => $bidder->id, 'contract_amount' => 845000,
            'notice_of_award_date' => now()->subDays(5)->toDateString(), 'status' => Award::STATUS_VALID, 'certificate_status' => Award::STATUS_VALID,
            'certificate_file_path' => $file, 'qr_token' => Str::random(40), 'verification_token' => Str::random(40),
        ]);
    };
});

it('shows the latest award with its document, and the other records as cards', function () {
    $older = ($this->award)('Concreting of Brgy. Bubog road', 'Bubog Builders', ['notice_of_award_date' => now()->subDays(30)->toDateString()], null);
    $latest = ($this->award)('Supply of office equipment', 'Sablayan Trading');
    ($this->award)('Withdrawn drainage works', 'Revoked Corp', ['status' => Award::STATUS_REVOKED, 'certificate_status' => Award::STATUS_REVOKED]);

    $response = testCase()->get(route('public.awards'))->assertOk();

    $response->assertSeeInOrder(['Notice of Award', 'Supply of office equipment', $latest->fresh()->certificate_number, 'Sablayan Trading', '₱845,000.00', 'Award postings'])
        ->assertSee(route('public.awards.document', $latest->qr_token), false)
        ->assertSee('Concreting of Brgy. Bubog road')
        ->assertSee('Document not available')
        ->assertDontSee('Withdrawn drainage works');

    // A record without a file never gets a document URL.
    expect($response->getContent())->not->toContain(route('public.awards.document', $older->qr_token));
});

it('selects an award from the link and shows the not-available state when it has no document', function () {
    ($this->award)('Supply of office equipment', 'Sablayan Trading');
    $noFile = ($this->award)('Concreting of Brgy. Bubog road', 'Bubog Builders', [], null);

    testCase()->get(route('public.awards', ['award' => $noFile->id]))->assertOk()
        ->assertSeeInOrder(['Notice of Award', 'Concreting of Brgy. Bubog road', 'Document not available', 'Bubog Builders']);
});

it('searches by project, contractor and reference number', function () {
    $office = ($this->award)('Supply of office equipment', 'Sablayan Trading');
    ($this->award)('Concreting of Brgy. Bubog road', 'Bubog Builders');

    testCase()->get(route('public.awards', ['q' => 'Bubog Builders']))->assertOk()
        ->assertSee('Concreting of Brgy. Bubog road')->assertDontSee('Supply of office equipment');
    testCase()->get(route('public.awards', ['q' => $office->fresh()->certificate_number]))->assertOk()
        ->assertSee('Supply of office equipment')->assertDontSee('Concreting of Brgy. Bubog road');
    testCase()->get(route('public.awards', ['q' => 'no-such-award']))->assertOk()
        ->assertSee('No published award matched');

    // The navbar search on this page searches awards, not procurement.
    testCase()->get(route('public.awards'))->assertSee('action="'.route('public.awards').'"', false);
});

it('streams only a valid, uploaded award document inline', function () {
    $valid = ($this->award)('Supply of office equipment', 'Sablayan Trading');
    $noFile = ($this->award)('Concreting of Brgy. Bubog road', 'Bubog Builders', [], null);
    $revoked = ($this->award)('Withdrawn drainage works', 'Revoked Corp', ['status' => Award::STATUS_REVOKED, 'certificate_status' => Award::STATUS_REVOKED], 'awards/revoked.pdf');

    $response = testCase()->get(route('public.awards.document', $valid->qr_token))->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('inline');

    testCase()->get(route('public.awards.document', $noFile->qr_token))->assertNotFound();
    testCase()->get(route('public.awards.document', $revoked->qr_token))->assertNotFound();
    testCase()->get(route('public.awards.document', 'not-a-token'))->assertNotFound();
});

it('shows an empty state when nothing has been published', function () {
    testCase()->get(route('public.awards'))->assertOk()->assertSee('No awarded contracts have been published yet.');
});
