<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security headers configuration
        $securityConfig = config('security.headers', []);

        // X-Frame-Options
        if (isset($securityConfig['x_frame_options'])) {
            $response->headers->set('X-Frame-Options', $securityConfig['x_frame_options']);
        }

        // X-Content-Type-Options
        if (isset($securityConfig['x_content_type_options'])) {
            $response->headers->set('X-Content-Type-Options', $securityConfig['x_content_type_options']);
        }

        // X-XSS-Protection
        if (isset($securityConfig['x_xss_protection'])) {
            $response->headers->set('X-XSS-Protection', $securityConfig['x_xss_protection']);
        }

        // Referrer Policy
        if (isset($securityConfig['referrer_policy'])) {
            $response->headers->set('Referrer-Policy', $securityConfig['referrer_policy']);
        }

        // Permissions Policy
        if (isset($securityConfig['permissions_policy']) && is_array($securityConfig['permissions_policy'])) {
            $permissionsPolicy = [];
            foreach ($securityConfig['permissions_policy'] as $directive => $allowlist) {
                $permissionsPolicy[] = $directive . '=' . $allowlist;
            }
            $response->headers->set('Permissions-Policy', implode(', ', $permissionsPolicy));
        }

        // Strict-Transport-Security (HSTS) for HTTPS
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Content Security Policy
        $cspConfig = config('security.content_security_policy', []);
        if ($cspConfig['enabled'] ?? false) {
            $cspDirectives = [];
            foreach ($cspConfig['directives'] as $directive => $value) {
                if ($directive === 'upgrade-insecure-requests' && $value) {
                    $cspDirectives[] = 'upgrade-insecure-requests';
                } else {
                    $cspDirectives[] = str_replace('_', '-', $directive) . ' ' . $value;
                }
            }

            $headerName = ($cspConfig['report_only'] ?? false) ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $response->headers->set($headerName, implode('; ', $cspDirectives));
        }

        // Remove server information
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
