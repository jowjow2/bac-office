<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the site footer on the public pages', function () {
    foreach (['/', '/procurement', '/awards', '/contact', '/about'] as $path) {
        testCase()->get($path)->assertOk()
            ->assertSee('Government links')->assertSee('Offices of the Municipality')
            ->assertSee('Philippine Standard Time')->assertSee('Municipality of San Jose, Occidental Mindoro');
    }
});
