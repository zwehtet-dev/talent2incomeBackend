<?php

namespace App\Providers;

use App\Services\SecurityService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(SecurityService::class, function ($app) {
            return new SecurityService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register custom validation rules for security
        $this->registerSecurityValidationRules();

        // Set up security event listeners
        $this->registerSecurityEventListeners();
    }

    /**
     * Register custom validation rules for security
     */
    protected function registerSecurityValidationRules(): void
    {
        // SQL injection prevention rule
        Validator::extend('no_sql_injection', function ($attribute, $value, $parameters, $validator) {
            if (! is_string($value)) {
                return true;
            }

            try {
                SecurityService::validateSqlParameters([$attribute => $value]);

                return true;
            } catch (\InvalidArgumentException $e) {
                return false;
            }
        });

        // XSS prevention rule
        Validator::extend('no_xss', function ($attribute, $value, $parameters, $validator) {
            if (! is_string($value)) {
                return true;
            }

            // Check for common XSS patterns
            $xssPatterns = [
                '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
                '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
                '/javascript:/i',
                '/on\w+\s*=/i',
                '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi',
                '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/mi',
            ];

            foreach ($xssPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return false;
                }
            }

            return true;
        });

        // Strong password rule
        Validator::extend('strong_password', function ($attribute, $value, $parameters, $validator) {
            if (! is_string($value)) {
                return false;
            }

            $config = config('security.password_policy', []);

            // Check minimum length
            if (strlen($value) < ($config['min_length'] ?? 8)) {
                return false;
            }

            // Check maximum length
            if (strlen($value) > ($config['max_length'] ?? 128)) {
                return false;
            }

            // Check for uppercase letter
            if (($config['require_uppercase'] ?? true) && ! preg_match('/[A-Z]/', $value)) {
                return false;
            }

            // Check for lowercase letter
            if (($config['require_lowercase'] ?? true) && ! preg_match('/[a-z]/', $value)) {
                return false;
            }

            // Check for numbers
            if (($config['require_numbers'] ?? true) && ! preg_match('/[0-9]/', $value)) {
                return false;
            }

            // Check for symbols
            if (($config['require_symbols'] ?? false) && ! preg_match('/[^A-Za-z0-9]/', $value)) {
                return false;
            }

            return true;
        });

        // Safe filename rule
        Validator::extend('safe_filename', function ($attribute, $value, $parameters, $validator) {
            if (! is_string($value)) {
                return false;
            }

            // Check for dangerous characters
            $dangerousChars = ['..', '/', '\\', ':', '*', '?', '"', '<', '>', '|', "\0"];
            foreach ($dangerousChars as $char) {
                if (str_contains($value, $char)) {
                    return false;
                }
            }

            // Check for reserved names (Windows)
            $reservedNames = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
            $nameWithoutExtension = pathinfo($value, PATHINFO_FILENAME);
            if (in_array(strtoupper($nameWithoutExtension), $reservedNames)) {
                return false;
            }

            return true;
        });

        // Register validation messages
        Validator::replacer('no_sql_injection', function ($message, $attribute, $rule, $parameters) {
            return 'The ' . $attribute . ' field contains potentially dangerous content.';
        });

        Validator::replacer('no_xss', function ($message, $attribute, $rule, $parameters) {
            return 'The ' . $attribute . ' field contains potentially dangerous content.';
        });

        Validator::replacer('strong_password', function ($message, $attribute, $rule, $parameters) {
            return 'The ' . $attribute . ' field must be a strong password.';
        });

        Validator::replacer('safe_filename', function ($message, $attribute, $rule, $parameters) {
            return 'The ' . $attribute . ' field must be a safe filename.';
        });
    }

    /**
     * Register security event listeners
     */
    protected function registerSecurityEventListeners(): void
    {
        // Listen for authentication events
        $this->app['events']->listen('Illuminate\Auth\Events\Login', function ($event) {
            SecurityService::logSecurityEvent('User login', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
            ]);
        });

        $this->app['events']->listen('Illuminate\Auth\Events\Failed', function ($event) {
            SecurityService::logSecurityEvent('Failed login attempt', [
                'email' => $event->credentials['email'] ?? 'unknown',
            ]);
        });

        $this->app['events']->listen('Illuminate\Auth\Events\Logout', function ($event) {
            SecurityService::logSecurityEvent('User logout', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
            ]);
        });

        $this->app['events']->listen('Illuminate\Auth\Events\PasswordReset', function ($event) {
            SecurityService::logSecurityEvent('Password reset', [
                'user_id' => $event->user->id,
                'email' => $event->user->email,
            ]);
        });
    }
}
