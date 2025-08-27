<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Track IP address and user agent for security monitoring
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Log authentication attempt
        Log::channel('auth')->info('Authentication attempt', [
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);

        // Check if user is authenticated
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();

            // Check if account is locked
            if ($user->isLocked()) {
                $timeRemaining = $user->getLockoutTimeRemaining();

                Log::channel('auth')->warning('Locked account access attempt', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $ipAddress,
                    'time_remaining' => $timeRemaining,
                ]);

                return response()->json([
                    'message' => 'Account is temporarily locked due to too many failed login attempts.',
                    'locked_until' => $user->locked_until,
                    'time_remaining' => $timeRemaining,
                ], 423); // 423 Locked
            }

            // Check if account is active
            if (! $user->is_active) {
                Log::channel('auth')->warning('Inactive account access attempt', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $ipAddress,
                ]);

                return response()->json([
                    'message' => 'Account is inactive. Please contact support.',
                ], 403);
            }

            // Track successful authentication
            $request->attributes->set('auth_ip', $ipAddress);
            $request->attributes->set('auth_user_agent', $userAgent);
        }

        return $next($request);
    }
}
