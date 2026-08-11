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
     * Forwards exactly the arguments it was given, and supplies 100 only when the
     * route named no limit at all.
     *
     * The argument count is load-bearing. The parent dispatches to named limiters
     * (`throttle:login`, registered by FortifyServiceProvider) with:
     *
     *     if (is_string($maxAttempts) && func_num_args() === 3 && ...)
     *
     * so an override that always passes five arguments makes `func_num_args()`
     * five, every named limiter falls through to the numeric path, and Fortify's
     * login throttle dies with "Rate limiter [login] is not defined."
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$args
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    public function handle($request, \Closure $next, ...$args)
    {
        if ($args === []) {
            $args = [100];
        }

        return parent::handle($request, $next, ...$args);
    }
}
