<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Client;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

test('security headers are present on responses', function () {
    $user = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

test('csp form-action includes passport client origins in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $user = User::factory()->create()->assignRole('admin');

    Client::create([
        'name' => 'Test App',
        'secret' => 'secret',
        'redirect_uris' => ['https://example.com/callback', 'https://other.com/auth'],
        'grant_types' => ['authorization_code'],
        'revoked' => false,
    ]);

    Cache::forget('csp_passport_origins');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("form-action 'self' https://example.com https://other.com");
});

test('csp form-action deduplicates origins from multiple clients', function () {
    app()->detectEnvironment(fn () => 'production');

    $user = User::factory()->create()->assignRole('admin');

    Client::create([
        'name' => 'App 1',
        'secret' => 'secret',
        'redirect_uris' => ['https://example.com/callback'],
        'grant_types' => ['authorization_code'],
        'revoked' => false,
    ]);
    Client::create([
        'name' => 'App 2',
        'secret' => 'secret',
        'redirect_uris' => ['https://example.com/other-path'],
        'grant_types' => ['authorization_code'],
        'revoked' => false,
    ]);

    Cache::forget('csp_passport_origins');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("form-action 'self' https://example.com;");
});

test('csp is not set in non-production environment', function () {
    $user = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($user)->get(route('dashboard'));

    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
    expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
});
