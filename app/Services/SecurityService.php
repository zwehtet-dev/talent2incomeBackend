<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SecurityService
{
    /**
     * Validate and sanitize SQL query parameters
     */
    public static function validateSqlParameters(array $parameters): array
    {
        $sanitized = [];

        foreach ($parameters as $key => $value) {
            if (is_string($value)) {
                // Check for SQL injection patterns
                if (self::containsSqlInjectionPatterns($value)) {
                    Log::warning('Potential SQL injection attempt detected', [
                        'parameter' => $key,
                        'value' => $value,
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ]);

                    throw new \InvalidArgumentException('Invalid parameter detected');
                }

                $sanitized[$key] = self::sanitizeSqlParameter($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Create safe database query with parameter binding
     */
    public static function safeQuery(string $query, array $bindings = []): \Illuminate\Database\Query\Builder
    {
        // Validate that query uses parameter binding
        if (! empty($bindings) && ! self::usesParameterBinding($query)) {
            throw new \InvalidArgumentException('Query must use parameter binding for security');
        }

        // Validate bindings
        $sanitizedBindings = self::validateSqlParameters($bindings);

        return DB::select($query, $sanitizedBindings);
    }

    /**
     * Validate file upload security
     */
    public static function validateFileUpload(\Illuminate\Http\UploadedFile $file): bool
    {
        $config = config('security.file_upload_security', []);

        // Check file size
        if ($file->getSize() > ($config['max_file_size'] ?? 10485760)) { // 10MB default
            return false;
        }

        // Check file extension
        $allowedExtensions = $config['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $allowedExtensions)) {
            return false;
        }

        // Check MIME type
        $allowedMimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
        ];

        $expectedMimeType = $allowedMimeTypes[$extension] ?? null;
        if ($expectedMimeType && $file->getMimeType() !== $expectedMimeType) {
            return false;
        }

        // Check for executable content in file headers
        if (self::containsExecutableContent($file)) {
            return false;
        }

        return true;
    }

    /**
     * Generate secure random token
     */
    public static function generateSecureToken(int $length = 32): string
    {
        return Str::random($length);
    }

    /**
     * Hash sensitive data
     */
    public static function hashSensitiveData(string $data): string
    {
        return hash('sha256', $data . config('app.key'));
    }

    /**
     * Validate IP address against whitelist/blacklist
     */
    public static function validateIpAddress(string $ip): bool
    {
        $config = config('security.ip_filtering', []);

        if (! ($config['enabled'] ?? false)) {
            return true;
        }

        // Check blacklist first
        $blacklist = array_filter($config['blacklist'] ?? []);
        if (! empty($blacklist) && in_array($ip, $blacklist)) {
            return false;
        }

        // Check whitelist
        $whitelist = array_filter($config['whitelist'] ?? []);
        if (! empty($whitelist) && ! in_array($ip, $whitelist)) {
            return false;
        }

        return true;
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        if (config('security.audit_logging.enabled', true)) {
            Log::channel('security')->info($event, array_merge($context, [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'timestamp' => now()->toISOString(),
                'user_id' => auth()->id(),
            ]));
        }
    }

    /**
     * Check if string contains SQL injection patterns
     */
    protected static function containsSqlInjectionPatterns(string $input): bool
    {
        $patterns = [
            // Common SQL injection patterns
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|EXECUTE|UNION|SCRIPT)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\b(OR|AND)\s+[\'"]?\w+[\'"]?\s*=\s*[\'"]?\w+[\'"]?)/i',
            '/(--|#|\/\*|\*\/)/i',
            '/(\bxp_cmdshell\b)/i',
            '/(\bsp_executesql\b)/i',
            '/(\bEXEC\s*\()/i',
            '/(\bCHAR\s*\()/i',
            '/(\bCONCAT\s*\()/i',
            '/(\bSUBSTRING\s*\()/i',
            '/(\bASCII\s*\()/i',
            '/(\bLEN\s*\()/i',
            '/(\bCAST\s*\()/i',
            '/(\bCONVERT\s*\()/i',
            '/(\bWAITFOR\s+DELAY)/i',
            '/(\bBENCHMARK\s*\()/i',
            '/(\bSLEEP\s*\()/i',
            '/(\bLOAD_FILE\s*\()/i',
            '/(\bINTO\s+OUTFILE)/i',
            '/(\bINTO\s+DUMPFILE)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize SQL parameter
     */
    protected static function sanitizeSqlParameter(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Remove or escape dangerous characters
        $input = str_replace(['--', '#', '/*', '*/'], '', $input);

        // Trim whitespace
        $input = trim($input);

        return $input;
    }

    /**
     * Check if query uses parameter binding
     */
    protected static function usesParameterBinding(string $query): bool
    {
        return preg_match('/\?|\:[a-zA-Z_][a-zA-Z0-9_]*/', $query);
    }

    /**
     * Check if file contains executable content
     */
    protected static function containsExecutableContent(\Illuminate\Http\UploadedFile $file): bool
    {
        $handle = fopen($file->getPathname(), 'rb');
        if (! $handle) {
            return true; // Assume dangerous if can't read
        }

        $header = fread($handle, 1024);
        fclose($handle);

        // Check for common executable signatures
        $executableSignatures = [
            "\x4D\x5A", // PE executable
            "\x7F\x45\x4C\x46", // ELF executable
            "\xCA\xFE\xBA\xBE", // Java class file
            "\xFE\xED\xFA\xCE", // Mach-O executable
            '#!/', // Shell script
            '<?php', // PHP script
            '<script', // JavaScript
        ];

        foreach ($executableSignatures as $signature) {
            if (str_contains($header, $signature)) {
                return true;
            }
        }

        return false;
    }
}
