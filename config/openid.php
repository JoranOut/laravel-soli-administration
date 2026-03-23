<?php

return [
    'passport' => [
        'tokens_can' => [
            'openid' => 'Enable OpenID Connect',
            'profile' => 'Access user profile (name)',
            'email' => 'Access user email address',
            'roles' => 'Access user roles',
        ],
    ],

    'custom_claim_sets' => [
        'roles' => [
            'roles',
        ],
    ],

    'repositories' => [
        'identity' => \App\OpenId\SoliIdentityRepository::class,
    ],

    'routes' => [
        'discovery' => true,
        'jwks' => true,
        'jwks_url' => '/oauth/jwks',
    ],

    'discovery' => [
        'hide_scopes' => false,
    ],

    'signer' => \Lcobucci\JWT\Signer\Rsa\Sha256::class,

    'token_headers' => [
        'kid' => 'soli-passport-key',
    ],

    'use_microseconds' => true,

    'issuedBy' => 'laravel',

    'forceHttps' => true,
];
