<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
});

it('shows the contact and citizen charter guide content', function () {
    $response = testCase()->get('/contact');

    $response->assertOk();
    $response->assertViewIs('pages.contact');
    $response->assertSee('Contact SJBAC');
    $response->assertSee('Contact the BAC Secretariat');
    $response->assertSee('Questions about a bidding, your registration or your documents?');
    $response->assertSee('Municipal Hall, San Jose, Occidental Mindoro');
    $response->assertSee('Monday to Friday, 8:00 AM to 5:00 PM');
    $response->assertSee('Send message');
    $response->assertSeeText('Citizen\'s Charter / Help Guide', false);
    $response->assertSee('How to Join Bidding');
    $response->assertSee('How to Register as Bidder');
    $response->assertSee('Required Documents');
    $response->assertSee('How to Download Bid Documents');
    $response->assertSee('How to Check Award Results');
    $response->assertSee('Business Permit');
    $response->assertSee('PhilGEPS Registration');
    $response->assertSeeText('Mayor\'s Permit', false);
    $response->assertSee('View Procurement Opportunities');
    $response->assertSee('Register as Bidder');
    $response->assertSee(route('public.procurement'), false);
    $response->assertSee(route('public.awards'), false);
});
