<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;
use Laravel\Passport\Client;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->isProduction()) {
            $nonce = Vite::cspNonce();
            $formAction = "'self' ".$this->passportClientOrigins();

            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Content-Security-Policy',
                "default-src 'self'; ".
                "script-src 'self' 'nonce-{$nonce}'; ".
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net; ".
                "font-src 'self' https://fonts.bunny.net; ".
                "img-src 'self' data:; ".
                "connect-src 'self'; ".
                "object-src 'none'; ".
                "base-uri 'self'; ".
                "form-action {$formAction}; ".
                "frame-ancestors 'none'"
            );
        }

        return $response;
    }

    /**
     * Get unique origins from all Passport client redirect URIs.
     */
    private function passportClientOrigins(): string
    {
        try {
            $origins = Cache::remember('csp_passport_origins', 86400, function () {
                return Client::query()
                    ->whereNotNull('redirect_uris')
                    ->pluck('redirect_uris')
                    ->flatten()
                    ->map(fn (string $uri) => parse_url(trim($uri), PHP_URL_SCHEME).'://'.parse_url(trim($uri), PHP_URL_HOST))
                    ->unique()
                    ->filter()
                    ->values()
                    ->all();
            });

            return implode(' ', $origins);
        } catch (\Throwable) {
            return '';
        }
    }
}
