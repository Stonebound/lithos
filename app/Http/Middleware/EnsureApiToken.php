<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('app.api_auth_key');
        if (! $apiKey) {
            return response()->json(['status' => 'error', 'message' => 'API Key not configured'], 500);
        }
        $headers = $request->headers;
        if (! $headers->has('api-key') || $headers->get('api-key') !== $apiKey) {
            return response()->json(['status' => 'error', 'message' => 'Forbidden: Invalid API Key'], 401);
        }

        return $next($request);
    }
}
