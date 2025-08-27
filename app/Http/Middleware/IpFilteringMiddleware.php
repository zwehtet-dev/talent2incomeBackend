<?php

namespace App\Http\Middleware;

use App\Services\SecurityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpFilteringMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if (! SecurityService::validateIpAddress($ip)) {
            SecurityService::logSecurityEvent('IP address blocked', [
                'ip' => $ip,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
