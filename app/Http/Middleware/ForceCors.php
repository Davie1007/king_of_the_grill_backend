<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ForceCors
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // define headers we want to force
        $headers = [
            'Access-Control-Allow-Origin'      => 'http://localhost:5173',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept',
            'Access-Control-Allow-Credentials' => 'true',
        ];

        // Log for debugging - you can remove later
        Log::info('ForceCors middleware running', ['method' => $request->method(), 'path' => $request->path()]);

        // If it's a preflight request, respond immediately with our headers
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json([], 204, $headers);
        }

        // Continue request and then attach headers to response
        $response = $next($request);

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}

