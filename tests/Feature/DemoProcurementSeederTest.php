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
