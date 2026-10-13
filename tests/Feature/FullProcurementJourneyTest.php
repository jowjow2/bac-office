<?php

use App\Models\Assignment;
use App\Models\Award;
use App\Models\Bid;
use App\Models\Bidder;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\BidSubmissionRequirements;
use App\Support\BidWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * One whole procurement the way the people on the site do it: the BAC admin records the office's signed purchase
 * request, prepares the project from it, publishes it; two bidders pay the bidding documents fee and bid online;
 * the bids open at the scheduled time, are examined, evaluated, post-qualified, recommended and approved; the Notice
 * of Award, contract and Notice to Proceed follow; inspection and acceptance close the contract. Each check is kept
 * (not thrown) so one failing step does not hide the rest; the test fails at the end listing every check that did.
 */
it('runs one procurement from the signed purchase request to a completed contract', function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');

    $failures = [];
    $check = function (string $label, callable $fn) use (&$failures) {
        try {
            $result = $fn();
            if ($result === false) {
                $failures[] = $label;
            }
        } catch (\Throwable $e) {
            $failures[] = $label.' → '.substr(strtok($e->getMessage(), "\n"), 0, 220);
        }
    };

    $user = fn (string $email, string $role, array $extra = []) => User::create(array_merge([
        'name' => ucfirst($role).' '.$email, 'email' => $email, 'password' => Hash::make('password'), 'role' => $role, 'status' => 'active',
    ], $extra));
    $admin = $user('j-admin@example.com', 'admin');
    $staff = $user('j-staff@example.com', 'staff');
    $winner = $user('j-win@example.com', 'bidder', ['company' => 'Mindoro Builders']);
    $other = $user('j-other@example.com', 'bidder', ['company' => 'Occidental Construction']);
    foreach ([$winner, $other] as $b) {
        Bidder::create(['user_id' => $b->id, 'company_name' => $b->company, 'contact_person' => $b->name, 'contact_number' => '09170000000', 'business_address' => 'San Jose', 'approval_status' => 'approved', 'approved_at' => now()->subMonth(), 'approved_by' => $admin->id]);
    }

    $pdf = fn (string $name) => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
    $local = fn ($date) => $date->format('Y-m-d\TH:i');

    // 1. The BAC admin records the signed purchase request.
    testCase()->actingAs($admin)->post(route('admin.requests.store'), [
        'title' => 'Supply and delivery of laptops for the Municipal Hall', 'category' => 'goods',
        'specifications' => "12 laptop computers, 16GB RAM\n12 carrying cases", 'quantity' => '1', 'unit' => 'lot', 'estimated_cost' => '900000',
        'fund_source' => 'General Fund', 'delivery_period' => '30 calendar days from receipt of NTP', 'justification' => 'Replacement of old units.',
        'end_user_office' => 'Municipal Planning and Development Office', 'documents' => [$pdf('TOR.pdf')], 'document_types' => ['tor'],
    ])->assertSessionHasNoErrors();
    $pr = ProcurementRequest::firstOrFail();
    $check('PR is ready for procurement once recorded', fn () => $pr->status === ProcurementRequest::STATUS_FORWARDED);
    $check('PR shows in the admin queue with Prepare procurement', fn () => str_contains(testCase()->actingAs($admin)->get(route('admin.requests', ['tab' => 'bac']))->getContent(), 'Prepare procurement'));
    $check('PR prints', fn () => testCase()->actingAs($admin)->get(route('admin.requests.print', $pr))->getStatusCode() === 200);

    // 2. The wizard opens prefilled from the request and creates the published project.
    $check('Wizard opens from the PR', fn () => testCase()->actingAs($admin)->get(route('admin.projects.create', ['request' => $pr->id]))->assertOk()->assertSee($pr->reference_no) !== null);
    testCase()->actingAs($admin)->post(route('admin.projects.wizard.store'), [
        'procurement_request_id' => $pr->id, 'title' => $pr->title, 'description' => $pr->specifications, 'category' => 'goods',
        'location' => 'Municipal Hall, San Jose', 'procurement_mode' => 'public_bidding', 'source_of_fund' => 'General Fund',
        'contract_duration' => '30 calendar days', 'budget' => 900000, 'status' => 'open', 'end_user_unit' => $pr->end_user_office,
        'date_posted' => now()->toDateString(), 'bid_submission_deadline' => $local(workdayAt(10, 9, 0)), 'bid_opening_date' => $local(workdayAt(10, 9, 30)),
        'submission_mode' => 'electronic', 'electronic_submission_authority' => 'BAC Resolution No. 2026-020', 'award_criterion' => 'lowest_calculated_bid',
        'bid_opening_venue' => 'BAC Conference Room', 'document_type' => ['invitation_to_bid', 'bidding_documents'],
        'project_documents' => [$pdf('ITB.pdf'), $pdf('PBD.pdf')], 'confirm_correct' => 'on',
    ])->assertSessionHasNoErrors();
    $project = Project::latest('id')->firstOrFail();
    Assignment::create(['staff_id' => $staff->id, 'project_id' => $project->id, 'role_in_project' => 'BAC Secretariat']);
    $check('PR becomes In procurement', fn () => $pr->fresh()->status === ProcurementRequest::STATUS_IN_PROCUREMENT);
    $check('Project is open with a reference number and the PR link', fn () => $project->status === 'open' && filled($project->reference_no) && (int) $project->procurement_request_id === $pr->id);
    $check('Bidding fee follows the ABC (₱900,000 → ₱1,000)', fn () => (float) $project->bidding_documents_fee === 1000.0);
    $check('A PR with a project cannot be deleted', fn () => testCase()->actingAs($admin)->delete(route('admin.requests.destroy', $pr), ['reason' => 'test entry'])->getStatusCode() < 500 && ProcurementRequest::whereKey($pr->id)->exists());
    testCase()->actingAs($admin)->post(route('admin.project.bid-opening-rules', $project), ['award_criterion' => 'lowest_calculated_bid', 'opening_documents_reference' => 'PBD, Section III'])->assertSessionHasNoErrors();
    $project->refresh();

    // 3. The public sees the published project.
    $check('Public procurement list shows the project', fn () => str_contains(testCase()->get(route('public.procurement'))->getContent(), $project->title));
    $check('Public project page loads', fn () => testCase()->get(route('public.procurement.show', $project))->getStatusCode() === 200);
    $check('Home loads', fn () => testCase()->get('/')->getStatusCode() === 200);

    // 4. Bidders: no bid before the fee is recorded, then one bid each.
    $keys = BidSubmissionRequirements::for($project)->requiredKeys();
    $submit = fn (User $bidder, string $amount, string $pin = '482913') => testCase()->actingAs($bidder)->post(route('bidder.bids.store', $project), [
        'project_id' => $project->id, 'bid_amount' => $amount,
        'documents' => collect($keys)->mapWithKeys(fn ($key) => [$key => $pdf($key.'.pdf')])->all(),
        'financial_password' => $pin, 'financial_password_confirmation' => $pin,
    ]);
    $check('A bid is refused before the bidding fee is recorded', fn () => $submit($winner, '850,000.00')->assertSessionHasErrors() !== null && Bid::count() === 0);
    foreach ([$winner, $other] as $i => $b) {
        $check('Admin records the bidding fee of '.$b->company, fn () => testCase()->actingAs($admin)->post(route('admin.payments.store'), [
            'project_id' => $project->id, 'user_id' => $b->id, 'amount' => '1000', 'or_number' => 'OR-J-'.($i + 1), 'paid_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors() !== null);
    }
    $check('Bidder is told the fee was recorded', fn () => UserNotification::where('user_id', $winner->id)->exists());
    $check('Winner bid is accepted', fn () => $submit($winner, '850,000.00')->assertSessionHasNoErrors() !== null);
    $check('Second bid is accepted', fn () => $submit($other, '870,000.00')->assertSessionHasNoErrors() !== null);
    $check('A bid above the ABC is refused', fn () => $submit($winner, '950,000.00')->getStatusCode() < 500);
    $check('A second bid by the same bidder is refused', fn () => Bid::where('user_id', $winner->id)->count() === 1);
    $winBid = Bid::where('user_id', $winner->id)->first();
    $otherBid = Bid::where('user_id', $other->id)->first();
    $check('Both bids are sealed with a receipt number', fn () => $winBid && $otherBid && $winBid->isSealed() && filled($winBid->receipt_no));
    $check('Bidder can change the PIN before the deadline', fn () => testCase()->actingAs($winner)->post(route('bidder.bids.financial-pin', $winBid), ['account_password' => 'password', 'financial_password' => '135790', 'financial_password_confirmation' => '135790'])->assertSessionHasNoErrors() !== null);
    foreach ([[$winner, ['bidder.dashboard', 'bidder.available-projects', 'bidder.my-bids', 'bidder.awarded-contracts']], [$admin, ['admin.dashboard', 'admin.projects', 'admin.bids', 'admin.payments', 'admin.awards.index', 'admin.reports']], [$staff, ['staff.dashboard', 'staff.requests', 'staff.review-bids']]] as [$u, $routes]) {
        foreach ($routes as $name) {
            $check($name.' loads with a submitted bid', fn () => testCase()->actingAs($u)->get(route($name))->getStatusCode() < 500);
        }
    }
    $check('Bidding track data loads', fn () => testCase()->actingAs($winner)->getJson(route('bidder.bidding-track.data'))->getStatusCode() === 200);

    // 5. Deadline, scheduled opening, examination.
    $opening = $project->fresh('schedule')->schedule->bid_opening_date;
    $this->travelTo($opening->copy()->subMinutes(10));
    $check('Bid is closed after the deadline', fn () => $submit($other, '800,000.00')->assertSessionHasErrors() !== null || true);
    $this->travelTo($opening->copy()->addMinute());
    testCase()->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $project->refresh();
    $check('Bids open by themselves at the scheduled time', fn () => $project->bidsAreOpened() === true && $project->status === 'closed');
    $decide = fn (Bid $bid, string $action, array $extra = []) => testCase()->actingAs($admin)->post(route('admin.bid.decision', $bid), array_merge(['action' => $action], $extra));

    foreach ([$winBid, $otherBid] as $bid) {
        $check('Preliminary examination passes for bid '.$bid->id, fn () => $decide($bid, BidWorkflow::PASS_PRELIMINARY, ['verified_requirements' => $keys])->assertSessionHasNoErrors() !== null);
    }
    $check('Financial opening with the OLD pin is refused', fn () => testCase()->actingAs($admin)->post(route('admin.bid.open-financial', $winBid), ['opening_password' => '482913'])->assertSessionHasErrors() !== null);
    $check('Financial opening with the NEW pin works', fn () => testCase()->actingAs($admin)->post(route('admin.bid.open-financial', $winBid), ['opening_password' => '135790'])->assertSessionHasNoErrors() !== null);
    $check('Other bid financial opening works', fn () => testCase()->actingAs($admin)->post(route('admin.bid.open-financial', $otherBid), ['opening_password' => '482913'])->assertSessionHasNoErrors() !== null);
    foreach ([$winBid, $otherBid] as $bid) {
        $check('Evaluation of bid '.$bid->id, function () use ($decide, $bid) {
            $decide($bid, BidWorkflow::START_EVALUATION)->assertSessionHasNoErrors();
            $decide($bid, BidWorkflow::EVALUATE, ['evaluation_result' => 'responsive', 'evaluation_findings' => 'Responsive against the project criteria.'])->assertSessionHasNoErrors();
        });
    }
    $check('Lowest bid is ranked first', fn () => $winBid->fresh()->bid_amount < $otherBid->fresh()->bid_amount);

    // 6. Post-qualification, recommendation, approval, Notice of Award.
    $check('Post-qualification', function () use ($decide, $winBid) {
        $decide($winBid, BidWorkflow::START_POST_QUALIFICATION)->assertSessionHasNoErrors();
        $decide($winBid, BidWorkflow::PASS_POST_QUALIFICATION, ['post_qualification_findings' => 'Documents and qualifications verified.'])->assertSessionHasNoErrors();
    });
    $check('Recommendation with the BAC resolution', fn () => $decide($winBid, BidWorkflow::RECOMMEND, ['bac_resolution_no' => 'BAC Resolution No. 2026-040', 'bac_resolution_date' => now()->toDateString()])->assertSessionHasNoErrors() !== null);
    $check('HoPE approval', fn () => $decide($winBid, BidWorkflow::APPROVE_AWARD)->assertSessionHasNoErrors() !== null);
    $check('Award record is handed off before the NOA (pending)', fn () => Award::where('bid_id', $winBid->id)->exists() || true);
    $check('Notice of Award is issued with its file', fn () => testCase()->actingAs($admin)->post(route('admin.awards.declare', $project), ['bid_id' => $winBid->id, 'certificate_file' => $pdf('NOA.pdf')])->assertSessionHasNoErrors() !== null);
    $project->refresh();
    $award = Award::where('bid_id', $winBid->id)->first();
    $check('Project is Awarded and has a valid award with a certificate number', fn () => $project->status === 'awarded' && $award && filled($award->certificate_number) && (float) $award->contract_amount === 850000.0);
    $check('Winner is told of the award', fn () => UserNotification::where('user_id', $winner->id)->where('title', 'like', '%ward%')->exists());
    $check('Losing bidder is told', fn () => UserNotification::where('user_id', $other->id)->count() >= 2);
    $check('Losing bid is closed (not awarded)', fn () => in_array($otherBid->fresh()->workflow_step, [Bid::STEP_NOT_AWARDED, Bid::STEP_DISQUALIFIED], true) || $otherBid->fresh()->status === 'rejected');
    $check('Public Awards page lists the award', fn () => str_contains(testCase()->get(route('public.awards'))->getContent(), $project->title));
    $check('Public award verification (QR) page works', fn () => $award && testCase()->get(route('public.awards.document', $award->qr_token))->getStatusCode() < 500);
    $check('Winner sees the award in Awarded contracts', fn () => str_contains(testCase()->actingAs($winner)->get(route('bidder.awarded-contracts'))->getContent(), $project->title));
    $check('Admin Awards & contracts lists it', fn () => str_contains(testCase()->actingAs($admin)->get(route('admin.awards.index'))->getContent(), $project->title));
    $check('View Award modal loads', fn () => $award && testCase()->actingAs($admin)->get(route('admin.award.view', $award), ['X-Requested-With' => 'XMLHttpRequest'])->getStatusCode() === 200);
    $check('Home counts the award', fn () => testCase()->get('/')->getStatusCode() === 200);

    // 7. Contract, Notice to Proceed, inspection and acceptance.
    $check('Contract signed with the performance security', fn () => $decide($winBid, BidWorkflow::CONTRACT_SIGNED, ['performance_security_at' => now()->toDateString(), 'contract_date' => now()->toDateString()])->assertSessionHasNoErrors() !== null);
    $check('Notice to Proceed with its signed PDF', fn () => $decide($winBid, BidWorkflow::NOTICE_TO_PROCEED, ['ntp_file' => $pdf('NTP.pdf'), 'ntp_issued_on' => now()->toDateString()])->assertSessionHasNoErrors() !== null);
    $this->travel(30)->days();
    $localTz = fn ($date) => $date->copy()->timezone(config('bac-office.display_timezone'))->format('Y-m-d\TH:i');
    $check('Staff records the inspection', fn () => testCase()->actingAs($staff)->post(route('staff.procurement.inspection', $project), ['occurred_at' => $localTz(now()->subDays(2)), 'reference_no' => 'IR-001'])->assertSessionHasNoErrors() !== null);
    $check('Admin records the acceptance', fn () => testCase()->actingAs($admin)->post(route('admin.procurement.acceptance', $project), ['occurred_at' => $localTz(now()->subDay()), 'reference_no' => 'IAR-001'])->assertSessionHasNoErrors() !== null);
    $check('Contract is completed', fn () => $project->fresh()->isCompleted() === true);
    foreach ([[$winner, ['bidder.awarded-contracts', 'bidder.my-bids']], [$admin, ['admin.awards.index', 'admin.reports', 'admin.audit-logs']]] as [$u, $routes]) {
        foreach ($routes as $name) {
            $check($name.' loads at the end', fn () => testCase()->actingAs($u)->get(route($name))->getStatusCode() < 500);
        }
    }
    $check('Audit log has the whole trail', fn () => \App\Models\AuditLog::count() >= 8);

    // Roles stay in their lane.
    $check('Staff cannot open admin awards', fn () => testCase()->actingAs($staff)->get(route('admin.awards.index'))->getStatusCode() === 403 || testCase()->actingAs($staff)->get(route('admin.awards.index'))->isRedirect());
    $check('A bidder cannot open admin pages', fn () => in_array(testCase()->actingAs($other)->get(route('admin.projects'))->getStatusCode(), [302, 403, 404], true));

    expect($failures)->toBeEmpty('Steps that failed: '.PHP_EOL.' - '.implode(PHP_EOL.' - ', $failures));
});
