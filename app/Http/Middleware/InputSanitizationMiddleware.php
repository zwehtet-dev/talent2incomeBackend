<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InputSanitizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize input data
        $this->sanitizeInput($request);

        // Validate content type for API requests
        if ($request->is('api/*') && config('security.api_security.validate_content_type', true)) {
            $this->validateContentType($request);
        }

        return $next($request);
    }

    /**
     * Sanitize input data to prevent XSS attacks
     */
    protected function sanitizeInput(Request $request): void
    {
        $input = $request->all();
        $sanitized = $this->sanitizeArray($input);
        $request->replace($sanitized);
    }

    /**
     * Recursively sanitize array data
     */
    protected function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $sanitizedKey = $this->sanitizeString($key);

            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeString($value);
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize string input
     */
    protected function sanitizeString(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Convert special characters to HTML entities to prevent XSS
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

        // Remove potentially dangerous HTML tags and attributes
        $input = strip_tags($input);

        // Trim whitespace
        $input = trim($input);

        return $input;
    }

    /**
     * Validate content type for API requests
     */
    protected function validateContentType(Request $request): void
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('DELETE')) {
            $contentType = $request->header('Content-Type');

            $allowedTypes = [
                'application/json',
                'application/x-www-form-urlencoded',
                'multipart/form-data',
            ];

            $isValidContentType = false;
            foreach ($allowedTypes as $type) {
                if (str_starts_with($contentType, $type)) {
                    $isValidContentType = true;

                    break;
                }
            }

            if (! $isValidContentType) {
                abort(415, 'Unsupported Media Type');
            }
        }
    }
}
