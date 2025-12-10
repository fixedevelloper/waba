<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;

class ApiKeyMiddleware
{
    public function handle($request, Closure $next)
    {
        $key = $request->header('X-API-KEY') ?? $request->query('api_key');
        if (! $key) {
            return response()->json(['error' => 'API key required'], 401);
        }


        $apiKey = ApiKey::where('key', $key)->first();
        if (! $apiKey || ! $apiKey->isValid()) {
            return response()->json(['error' => 'Invalid or exhausted API key'], 403);
        }


// increment usage (simple approach)
        $apiKey->increment('used');


// bind apiKey to request
        $request->attributes->set('api_key_model', $apiKey);


        return $next($request);
    }
}
