<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersionMiddleware
{
    /**
     * Handle an incoming request and set API version context.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next, string $version = 'v1'): Response
    {
        // Set the API version in the request for controllers to use
        $request->attributes->set('api_version', $version);

        // Add version header to response
        $response = $next($request);

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $response->header('X-API-Version', $version);
            $response->header('X-API-Supported-Versions', 'v1');
        }

        return $response;
    }
}
