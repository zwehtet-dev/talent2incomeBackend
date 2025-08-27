<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    public function __construct(private AuditService $auditService)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // Duration in milliseconds

        // Only audit API requests and sensitive operations
        if ($this->shouldAudit($request, $response)) {
            $this->auditRequest($request, $response, $duration);
        }

        return $response;
    }

    /**
     * Determine if the request should be audited.
     */
    private function shouldAudit(Request $request, Response $response): bool
    {
        // Always audit API requests
        if ($request->is('api/*')) {
            return true;
        }

        // Audit authentication-related requests
        if ($request->is('login', 'register', 'logout', 'password/*')) {
            return true;
        }

        // Audit admin requests
        if ($request->is('admin/*')) {
            return true;
        }

        // Audit failed requests (4xx, 5xx status codes)
        if ($response->getStatusCode() >= 400) {
            return true;
        }

        return false;
    }

    /**
     * Audit the request.
     */
    private function auditRequest(Request $request, Response $response, float $duration): void
    {
        $user = Auth::user();
        $statusCode = $response->getStatusCode();

        // Determine event type based on request
        $eventType = $this->determineEventType($request, $response);

        // Determine severity based on status code and request type
        $severity = $this->determineSeverity($request, $response);

        // Check if request contains sensitive data
        $isSensitive = $this->isSensitiveRequest($request);

        $this->auditService->log(
            eventType: $eventType,
            description: $this->generateDescription($request, $response),
            severity: $severity,
            isSensitive: $isSensitive,
            metadata: [
                'http_status' => $statusCode,
                'response_time_ms' => $duration,
                'route_name' => $request->route()?->getName(),
                'route_action' => $request->route()?->getActionName(),
                'request_size' => strlen($request->getContent()),
                'response_size' => strlen($response->getContent()),
            ]
        );
    }

    /**
     * Determine the event type based on the request.
     */
    private function determineEventType(Request $request, Response $response): string
    {
        $method = $request->method();
        $path = $request->path();
        $statusCode = $response->getStatusCode();

        // Authentication events
        if (str_contains($path, 'auth/login')) {
            return $statusCode < 400 ? 'auth.login_success' : 'auth.login_failed';
        }

        if (str_contains($path, 'auth/register')) {
            return $statusCode < 400 ? 'auth.register_success' : 'auth.register_failed';
        }

        if (str_contains($path, 'auth/logout')) {
            return 'auth.logout';
        }

        // API events
        if ($request->is('api/*')) {
            $resource = $this->extractResourceFromPath($path);

            return match ($method) {
                'GET' => "api.{$resource}.read",
                'POST' => "api.{$resource}.create",
                'PUT', 'PATCH' => "api.{$resource}.update",
                'DELETE' => "api.{$resource}.delete",
                default => "api.{$resource}.{$method}",
            };
        }

        // Admin events
        if ($request->is('admin/*')) {
            return 'admin.' . str_replace('/', '.', trim(str_replace('admin', '', $path), '/'));
        }

        // Error events
        if ($statusCode >= 400) {
            return match (true) {
                $statusCode >= 500 => 'error.server_error',
                $statusCode >= 400 => 'error.client_error',
                default => 'error.unknown',
            };
        }

        return 'request.' . strtolower($method);
    }

    /**
     * Extract resource name from API path.
     */
    private function extractResourceFromPath(string $path): string
    {
        $segments = explode('/', trim($path, '/'));

        // Remove 'api' from segments
        $segments = array_filter($segments, fn ($segment) => $segment !== 'api');

        // Get the first segment as resource name
        return $segments[0] ?? 'unknown';
    }

    /**
     * Determine severity based on request and response.
     */
    private function determineSeverity(Request $request, Response $response): string
    {
        $statusCode = $response->getStatusCode();

        // Critical for server errors
        if ($statusCode >= 500) {
            return 'critical';
        }

        // Error for client errors
        if ($statusCode >= 400) {
            return 'error';
        }

        // Warning for authentication failures
        if (str_contains($request->path(), 'auth') && $statusCode >= 400) {
            return 'warning';
        }

        // Warning for admin actions
        if ($request->is('admin/*')) {
            return 'warning';
        }

        // Info for everything else
        return 'info';
    }

    /**
     * Check if request contains sensitive data.
     */
    private function isSensitiveRequest(Request $request): bool
    {
        // Authentication requests are always sensitive
        if (str_contains($request->path(), 'auth')) {
            return true;
        }

        // Payment requests are sensitive
        if (str_contains($request->path(), 'payment')) {
            return true;
        }

        // Admin requests are sensitive
        if ($request->is('admin/*')) {
            return true;
        }

        // GDPR requests are sensitive
        if (str_contains($request->path(), 'gdpr')) {
            return true;
        }

        // Check for sensitive fields in request data
        $sensitiveFields = ['password', 'token', 'api_key', 'secret', 'credit_card', 'ssn'];
        $requestData = $request->all();

        foreach ($sensitiveFields as $field) {
            if (isset($requestData[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a human-readable description.
     */
    private function generateDescription(Request $request, Response $response): string
    {
        $method = $request->method();
        $path = $request->path();
        $statusCode = $response->getStatusCode();
        $user = Auth::user();

        $userInfo = $user ? "User {$user->id}" : 'Anonymous user';
        $statusText = $statusCode < 400 ? 'successful' : 'failed';

        return "{$userInfo} made {$statusText} {$method} request to {$path} (HTTP {$statusCode})";
    }
}
