<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Raises the default rate limit for middleware declared as a bare `throttle`.
 *
 * Passport registers its routes with `'middleware' => 'throttle'` and no
 * arguments (vendor/laravel/passport/routes/web.php), so `/oauth/token` and
 * `/oauth/authorize` inherit Laravel's 60-per-minute default. That number was
 * never chosen for this application.
 *
 * Sixty is generous for the traffic it actually sees — a token exchange happens
 * once per login, not once per page view, so a member generates about one call a
 * day. The limit still matters because every WordPress client shares one server
 * IP, which makes it a per-site ceiling rather than a per-user one: one client
 * looping on a failed login consumes the allowance for every other member of
 * that site.
 *
 * The clients authenticate with a secret, so the throttle is defence in depth
 * here, not the access control. 100/minute keeps that without making a
 * misbehaving client cheap to notice.
 *
 * Routes that pass explicit arguments (`throttle:6,1` in routes/settings.php,
 * `throttle:500,30` in routes/api.php) are unaffected — their values override
 * these defaults.
 */
class ThrottleRequestsWithHigherDefault extends ThrottleRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  int|string  $maxAttempts
     * @param  float|int  $decayMinutes
     * @param  string  $prefix
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    public function handle($request, \Closure $next, $maxAttempts = 100, $decayMinutes = 1, $prefix = '')
    {
        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }
}
