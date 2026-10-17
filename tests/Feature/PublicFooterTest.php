<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the site footer on the public pages', function () {
    foreach (['/', '/procurement', '/awards', '/contact', '/about'] as $path) {
        testCase()->get($path)->assertOk()
            ->assertSee('Government links')->assertDontSee('Offices of the Municipality')
            ->assertSee('Philippine Standard Time')->assertSee('Municipality of San Jose, Occidental Mindoro');
    }
});

it('shows the Facebook link of the BAC only when its address is set', function () {
    config(['bac-office.social.facebook' => null]);
    testCase()->get('/')->assertOk()->assertDontSee('on Facebook');

    config(['bac-office.social.facebook' => 'https://www.facebook.com/example-bac']);
    testCase()->get('/')->assertOk()->assertSee('https://www.facebook.com/example-bac', false)->assertSee('Bids and Awards Committee on Facebook');
});

it('links to the Facebook page of the BAC by default', function () {
    testCase()->get('/')->assertOk()->assertSee('https://www.facebook.com/bac.san.jose.oksimin', false);
});

it('keeps the search box in the navigation, with a plain Search label on the home page', function () {
    testCase()->get('/')->assertOk()->assertSee('placeholder="Search..."', false)->assertDontSee('Search procurement...');
    // Procurement and Awards keep only their own, larger search box.
    testCase()->get('/procurement')->assertOk()->assertDontSee('Search procurement...', false)->assertSee('class="board-search"', false);
    testCase()->get('/awards')->assertOk()->assertDontSee('Search awards or contractors...', false)->assertSee('class="board-search"', false);
});
