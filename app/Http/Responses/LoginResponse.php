<?php

namespace App\Http\Responses;

use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $intended = session()->pull('url.intended', config('fortify.home'));

        if (! $this->isSafeRedirect($intended)) {
            $intended = config('fortify.home');
        }

        // OAuth authorize redirects need full page navigation to allow
        // cross-origin redirects to the OAuth callback URL.
        if (str_contains($intended, '/oauth/authorize')) {
            return Inertia::location($intended);
        }

        return redirect($intended);
    }

    private function isSafeRedirect(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return true;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host === 'soli.nl' || str_ends_with((string) $host, '.soli.nl');
    }
}
