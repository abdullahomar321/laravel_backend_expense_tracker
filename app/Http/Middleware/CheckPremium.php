<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPremium
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid Bearer token.',
            ], 401);
        }

        // Always read fresh from DB — never trust cached auth object
        $freshUser = $user->fresh();

        if (! $freshUser || ! $freshUser->is_premium) {
            return response()->json([
                'success' => false,
                'message' => 'This feature requires a premium subscription.',
            ], 403);
        }

        return $next($request);
    }
}
