<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders admin navigation destinations as complete documents with styles ready before the body', function (string $route) {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $response = testCase()->actingAs($admin)->get(route($route))->assertOk();
    $html = $response->getContent();

    expect(ltrim($html))->toStartWith('<!DOCTYPE html>');

    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($document);

    expect($xpath->query('/html/head/link[@rel="stylesheet"]')->length)->toBeGreaterThan(0)
        ->and($xpath->query('/html/body//link[@rel="stylesheet"]')->length)->toBe(0)
        ->and($xpath->query('//*[@id="portalSidebar"]')->length)->toBe(1)
        ->and($xpath->query('/html/head/link[@rel="expect" and @href="#portal-page-ready" and @blocking="render"]')->length)->toBe(1)
        ->and($xpath->query('/html/body//*[@id="portal-page-ready"]')->length)->toBe(1);
})->with([
    'dashboard' => 'admin.dashboard',
    'purchase requests' => 'admin.requests',
    'projects and biddings' => 'admin.projects',
    'bid management' => 'admin.bids',
    'fee payments' => 'admin.payments',
    'awards and contracts' => 'admin.awards.index',
    'users' => 'admin.users',
    'staff assignments' => 'admin.assignments',
    'reports' => 'admin.reports',
    'audit logs' => 'admin.audit-logs',
    'about' => 'admin.settings.about',
    'messages' => 'admin.messages',
    'notifications' => 'admin.notifications',
]);
