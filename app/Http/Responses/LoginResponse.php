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

        // OAuth authorize redirects need full page navigation to allow
        // cross-origin redirects to the OAuth callback URL.
        if (str_contains($intended, '/oauth/authorize')) {
            return Inertia::location($intended);
        }

        return redirect($intended);
    }
}
