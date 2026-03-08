<?php

test('home redirects to login for guests', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});
