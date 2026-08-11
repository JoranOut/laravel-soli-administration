<?php

use Illuminate\Support\Facades\RateLimiter;

/**
 * The OAuth endpoints inherit their rate limit from Passport's bare `throttle`
 * middleware. These tests pin the raised default, and pin that routes declaring
 * their own limits are not dragged along with it.
 *
 * Context: on 2026-08-10 SSO on dev.soli.nl failed for an afternoon because
 * /oauth/token returned 429 to every login. The cause was a corrupted counter in
 * the file cache store, not the limit itself — but the investigation surfaced that
 * nobody had ever chosen 60/minute for an endpoint every client shares by IP.
 */
beforeEach(function () {
    RateLimiter::clear('');
    cache()->flush();
});

it('allows more than Laravel\'s default 60 token requests per minute', function () {
    // 61 is the assertion that matters: the request that would have been
    // rejected under the framework default must get through.
    for ($i = 0; $i < 61; $i++) {
        $response = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => 'invalid-code',
        ]);

        expect($response->getStatusCode())->not->toBe(429);
    }
});

it('still throttles the token endpoint once the raised limit is passed', function () {
    // Fails closed: raising the ceiling must not remove it.
    $statuses = [];

    for ($i = 0; $i < 105; $i++) {
        $statuses[] = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => 'invalid-code',
        ])->getStatusCode();
    }

    expect($statuses)->toContain(429);
});

it('leaves routes that declare their own limit alone', function () {
    // routes/api.php uses throttle:500,30. If the alias override leaked its
    // defaults into explicit declarations, this would 429 long before 500.
    for ($i = 0; $i < 105; $i++) {
        $response = $this->putJson('/api/v1/sync/members/1000', []);

        expect($response->getStatusCode())->not->toBe(429);
    }
});

it('still resolves named rate limiters', function () {
    // The alias override must not break `throttle:login`, which Fortify registers
    // by name. The parent only takes its named-limiter path when handle() receives
    // exactly three arguments, so an override that always forwards five silently
    // routes every named limiter into the numeric path and throws
    // "Rate limiter [login] is not defined." CI caught exactly that.
    expect(RateLimiter::limiter('login'))->not->toBeNull();

    $user = \App\Models\User::factory()->create();

    // Fortify limits login to 5 per minute by email+IP.
    for ($i = 0; $i < 6; $i++) {
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    expect($response->getStatusCode())->toBe(429);
});

it('returns JSON rather than HTML when the caller asks for it', function () {
    // The WordPress client reports any non-JSON token response as the generic
    // `invalid-token`, discarding the status. An HTML 429 is what made this
    // outage unreadable, so a throttled response must stay machine-readable.
    for ($i = 0; $i < 105; $i++) {
        $response = $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => 'invalid-code',
        ]);

        if ($response->getStatusCode() === 429) {
            expect($response->headers->get('content-type'))->toContain('json');
            expect(json_decode($response->getContent(), true))->toBeArray();

            return;
        }
    }

    $this->fail('The token endpoint never throttled, so the JSON shape was never asserted.');
});
