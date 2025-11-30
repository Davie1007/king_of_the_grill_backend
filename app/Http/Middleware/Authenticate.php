<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request)
    {
        if ($request->expectsJson()) {
            // For API requests, return null to avoid redirection
            return null;
        }

        // For web requests (if any), redirect to a web login route
        return '/login'; // Adjust if you add a web login route later
    }

    /**
     * Handle an unauthenticated user.
     */
    protected function unauthenticated($request, array $guards)
    {
        if ($request->expectsJson()) {
            // Return JSON response for API requests
            abort(response()->json([
                'error' => 'Unauthenticated',
                'message' => 'You need to be authenticated to access this resource.',
            ], 401));
        }

        parent::unauthenticated($request, $guards);
    }
}