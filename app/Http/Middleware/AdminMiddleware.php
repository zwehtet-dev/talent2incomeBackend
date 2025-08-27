<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): ResponseAlias
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json([
                'message' => 'Access denied. Admin privileges required.',
                'status_code' => 403,
            ], 403);
        }

        return $next($request);
    }
}
