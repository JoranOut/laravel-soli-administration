<?php

test('registration is disabled', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});
