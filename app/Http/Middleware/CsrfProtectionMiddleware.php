<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CsrfProtectionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip CSRF protection for GET, HEAD, OPTIONS requests
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // Skip CSRF protection for API routes using Sanctum
        if ($request->is('api/*') && $request->bearerToken()) {
            return $next($request);
        }

        // Validate CSRF token
        if (! $this->validateCsrfToken($request)) {
            abort(419, 'CSRF token mismatch');
        }

        return $next($request);
    }

    /**
     * Generate CSRF token
     */
    public static function generateToken(): string
    {
        return Str::random(40);
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrfToken(Request $request): bool
    {
        $token = $this->getTokenFromRequest($request);

        if (! $token) {
            return false;
        }

        $sessionToken = $request->session()->token();

        if (! $sessionToken) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Get CSRF token from request
     */
    protected function getTokenFromRequest(Request $request): ?string
    {
        // Check X-CSRF-TOKEN header
        $token = $request->header('X-CSRF-TOKEN');

        if (! $token) {
            // Check X-XSRF-TOKEN header (for frameworks like Angular)
            $token = $request->header('X-XSRF-TOKEN');
        }

        if (! $token) {
            // Check _token field in request body
            $token = $request->input('_token');
        }

        return $token;
    }
}
