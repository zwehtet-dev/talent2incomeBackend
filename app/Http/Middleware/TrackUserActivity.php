<?php

namespace App\Http\Middleware;

use App\Services\OnlineStatusService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function __construct(
        private OnlineStatusService $onlineStatusService
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Track user activity if authenticated
        if ($request->user()) {
            $this->onlineStatusService->updateUserActivity($request->user());
        }

        return $response;
    }
}
