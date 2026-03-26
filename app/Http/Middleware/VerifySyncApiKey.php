<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySyncApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');
        $validKey = config('services.soli_sync.api_key');

        if (! $apiKey || ! $validKey || ! hash_equals($validKey, $apiKey)) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        return $next($request);
    }
}
