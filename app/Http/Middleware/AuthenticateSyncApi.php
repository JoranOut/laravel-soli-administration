<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSyncApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('services.sync.api_key');

        if (empty($configuredKey)) {
            Log::error('Sync API: request rejected — API key not configured on server.');

            return response()->json(['message' => 'Sync API is not configured.'], 503);
        }

        $providedKey = $request->bearerToken();

        if (! $providedKey || ! hash_equals($configuredKey, $providedKey)) {
            Log::warning('Sync API: invalid API key attempt', ['ip' => $request->ip(), 'path' => $request->path()]);

            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        return $next($request);
    }
}
