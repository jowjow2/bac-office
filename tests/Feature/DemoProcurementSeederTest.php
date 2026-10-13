<?php

use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DemoProcurementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds 20 purchase requests and 20 projects, none named as a test, and only once', function () {
    User::create(['name' => 'BAC Admin', 'email' => 'admin@example.org', 'password' => 'x-not-used-1', 'role' => 'admin', 'status' => 'active']);

    (new DemoProcurementSeeder)->run();
    (new DemoProcurementSeeder)->run();

    $requests = ProcurementRequest::where('reference_no', 'like', 'PR-2026-D%')->get();
    $projects = Project::where('reference_no', 'like', 'SJ-BAC-2026-%-D__')->get();

    expect($requests)->toHaveCount(20)->and($projects)->toHaveCount(20);
    expect($requests->pluck('title')->merge($projects->pluck('title'))->filter(fn ($t) => stripos($t, 'test') !== false))->toBeEmpty();
    expect($projects->where('status', 'open')->every(fn ($p) => $p->deadline->isFuture()))->toBeTrue()->and($projects->where('status', 'open')->count())->toBeGreaterThan(10);
    expect($projects->whereNotNull('procurement_request_id'))->toHaveCount(6);
    expect($projects->every(fn ($p) => (float) $p->bidding_documents_fee === (float) \App\Support\BiddingDocumentsFee::maximumFor($p->budget)))->toBeTrue();
    expect(ProcurementRequest::nextReferenceNo())->not->toContain('D0');
});

it('removes only the demo records', function () {
    User::create(['name' => 'BAC Admin', 'email' => 'admin@example.org', 'password' => 'x-not-used-1', 'role' => 'admin', 'status' => 'active']);
    (new DemoProcurementSeeder)->run();

    putenv('DEMO_PROCUREMENT=remove');
    try {
        (new DemoProcurementSeeder)->run();
    } finally {
        putenv('DEMO_PROCUREMENT');
    }

    expect(ProcurementRequest::count())->toBe(0)->and(Project::count())->toBe(0);
});

it('adds 30 awarded projects with their bids and awards, posted publicly and listed for the BAC', function () {
    $admin = User::create(['name' => 'BAC Admin', 'email' => 'admin@example.org', 'password' => 'x-not-used-1', 'role' => 'admin', 'status' => 'active']);

    (new \Database\Seeders\DemoAwardedSeeder)->run();
    (new \Database\Seeders\DemoAwardedSeeder)->run();

    $projects = Project::where('status', 'awarded')->where('reference_no', 'like', 'SJ-BAC-2026-%-D__')->get();
    expect($projects)->toHaveCount(30)
        ->and(\App\Models\Bid::count())->toBe(90)
        ->and(\App\Models\Award::count())->toBe(30)
        ->and($projects->pluck('title')->filter(fn ($t) => stripos($t, 'test') !== false))->toBeEmpty();
    expect(\App\Models\Award::publiclyPosted()->count())->toBe(30);
    expect(\App\Models\Award::with('bid')->get()->every(fn ($a) => (float) $a->contract_amount === (float) $a->bid->bid_amount && (float) $a->contract_amount <= (float) $a->project->budget))->toBeTrue();

    testCase()->actingAs($admin)->get(route('admin.awards.index'))->assertOk()->assertSee($projects->first()->title);
    testCase()->get(route('public.awards'))->assertOk();

    putenv('DEMO_PROCUREMENT=remove');
    try {
        (new DemoProcurementSeeder)->run();
    } finally {
        putenv('DEMO_PROCUREMENT');
    }
    expect(Project::count())->toBe(0)->and(\App\Models\Bid::count())->toBe(0)->and(\App\Models\Award::count())->toBe(0);
});
