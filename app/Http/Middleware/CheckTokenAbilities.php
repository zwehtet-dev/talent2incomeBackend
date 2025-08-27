<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAbilities
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     * @param string ...$abilities
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        $token = $user->currentAccessToken();

        if (! $token) {
            Log::channel('security')->warning('Request without valid token', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'message' => 'Invalid token',
            ], 401);
        }

        // Check if token has required abilities
        foreach ($abilities as $ability) {
            if (! $token->can($ability)) {
                Log::channel('security')->warning('Insufficient token abilities', [
                    'user_id' => $user->id,
                    'required_ability' => $ability,
                    'token_abilities' => $token->abilities,
                    'ip' => $request->ip(),
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => 'Insufficient permissions',
                    'required_ability' => $ability,
                ], 403);
            }
        }

        return $next($request);
    }
}
