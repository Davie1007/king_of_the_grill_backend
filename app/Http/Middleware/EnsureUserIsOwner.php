<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || strtolower($user->role) !== 'Owner') {
            return response()->json([
                'message' => 'Access denied. Only the owner can access this resource.'
            ], 403);
        }

        return $next($request);
    }
}
