<?php

test('discovery endpoint returns openid configuration', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    $response->assertOk();
    $response->assertJsonStructure([
        'issuer',
        'authorization_endpoint',
        'token_endpoint',
        'jwks_uri',
        'scopes_supported',
        'response_types_supported',
        'grant_types_supported',
    ]);

    $data = $response->json();

    expect($data['scopes_supported'])->toContain('openid');
    expect($data['scopes_supported'])->toContain('profile');
    expect($data['scopes_supported'])->toContain('email');
    expect($data['scopes_supported'])->toContain('roles');
});

test('jwks endpoint returns key set', function () {
    $response = $this->getJson('/oauth/jwks');

    $response->assertOk();
    $response->assertJsonStructure([
        'keys',
    ]);
});
