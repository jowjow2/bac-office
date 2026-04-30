<?php

test('the application returns a successful response', function () {
    $response = testCase()->get('/');

    $response->assertStatus(200);
});
