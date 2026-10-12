<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => testCase()->withoutVite());

it('describes the public pages to search engines and keeps tokenized pages out', function () {
    testCase()->get('/')->assertOk()
        ->assertSee('name="description"', false)
        ->assertSee('rel="canonical"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('content="index,follow"', false);

    testCase()->get('/procurement')->assertOk()->assertSee('content="index,follow"', false);
});

it('serves a sitemap of the public pages', function () {
    $response = testCase()->get('/sitemap.xml')->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/xml');
    $response->assertSee('<urlset', false)->assertSee(url('/procurement'), false)->assertSee(url('/awards'), false);
});

it('points crawlers at the sitemap and away from the private areas', function () {
    $robots = file_get_contents(public_path('robots.txt'));
    expect($robots)->toContain('Sitemap: https://bacsanjoselgu.com/sitemap.xml')
        ->toContain('Disallow: /admin')->toContain('Disallow: /bidder')->toContain('Disallow: /staff');
});

it('counts visits to the public pages only on the live site', function () {
    testCase()->get('/')->assertOk()->assertDontSee('/_vercel/insights/script.js', false);

    app()->detectEnvironment(fn () => 'production');
    testCase()->get('/')->assertOk()->assertSee('/_vercel/insights/script.js', false);
    testCase()->get('/privacy')->assertOk()->assertSee('Vercel Web Analytics');
});
