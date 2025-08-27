<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SecurityAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:audit {--fix : Attempt to fix security issues}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a security audit of the application configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting security audit...');

        $issues = [];
        $warnings = [];

        // Check environment configuration
        $this->checkEnvironmentSecurity($issues, $warnings);

        // Check file permissions
        $this->checkFilePermissions($issues, $warnings);

        // Check security headers configuration
        $this->checkSecurityHeaders($issues, $warnings);

        // Check encryption settings
        $this->checkEncryption($issues, $warnings);

        // Check session security
        $this->checkSessionSecurity($issues, $warnings);

        // Check CORS configuration
        $this->checkCorsConfiguration($issues, $warnings);

        // Display results
        $this->displayResults($issues, $warnings);

        return empty($issues) ? 0 : 1;
    }

    protected function checkEnvironmentSecurity(array &$issues, array &$warnings): void
    {
        $this->info('Checking environment security...');

        // Check if APP_DEBUG is disabled in production
        if (config('app.env') === 'production' && config('app.debug')) {
            $issues[] = 'APP_DEBUG should be false in production';
        }

        // Check if APP_KEY is set
        if (empty(config('app.key'))) {
            $issues[] = 'APP_KEY is not set';
        }

        // Check if HTTPS is required in production
        if (config('app.env') === 'production' && ! config('security.api_security.require_https')) {
            $warnings[] = 'HTTPS should be required in production';
        }

        // Check if strong password policy is enabled
        $passwordConfig = config('security.password_policy');
        if (! $passwordConfig['require_uppercase'] || ! $passwordConfig['require_lowercase'] || ! $passwordConfig['require_numbers']) {
            $warnings[] = 'Password policy could be stronger';
        }
    }

    protected function checkFilePermissions(array &$issues, array &$warnings): void
    {
        $this->info('Checking file permissions...');

        $sensitiveFiles = [
            '.env',
            'config/',
            'storage/',
            'bootstrap/cache/',
        ];

        foreach ($sensitiveFiles as $file) {
            $path = base_path($file);
            if (File::exists($path)) {
                $permissions = substr(sprintf('%o', fileperms($path)), -4);

                // Check if files are world-readable
                if (substr($permissions, -1) >= '4') {
                    $warnings[] = "File {$file} is world-readable (permissions: {$permissions})";
                }
            }
        }
    }

    protected function checkSecurityHeaders(array &$issues, array &$warnings): void
    {
        $this->info('Checking security headers configuration...');

        $headers = config('security.headers');

        if ($headers['x_frame_options'] !== 'DENY' && $headers['x_frame_options'] !== 'SAMEORIGIN') {
            $warnings[] = 'X-Frame-Options should be set to DENY or SAMEORIGIN';
        }

        if ($headers['x_content_type_options'] !== 'nosniff') {
            $warnings[] = 'X-Content-Type-Options should be set to nosniff';
        }

        // Check CSP configuration
        $csp = config('security.content_security_policy');
        if (! $csp['enabled'] && config('app.env') === 'production') {
            $warnings[] = 'Content Security Policy should be enabled in production';
        }
    }

    protected function checkEncryption(array &$issues, array &$warnings): void
    {
        $this->info('Checking encryption settings...');

        // Check session encryption
        if (! config('session.encrypt')) {
            $warnings[] = 'Session encryption should be enabled';
        }

        // Check cookie security
        if (! config('session.http_only')) {
            $issues[] = 'Session cookies should be HTTP only';
        }

        if (config('app.env') === 'production' && ! config('session.secure')) {
            $warnings[] = 'Session cookies should be secure in production';
        }
    }

    protected function checkSessionSecurity(array &$issues, array &$warnings): void
    {
        $this->info('Checking session security...');

        $sessionConfig = config('security.session_security');

        if (! $sessionConfig['regenerate_on_login']) {
            $warnings[] = 'Session should be regenerated on login';
        }

        if (! $sessionConfig['invalidate_on_logout']) {
            $warnings[] = 'Session should be invalidated on logout';
        }

        if ($sessionConfig['timeout_minutes'] > 480) { // 8 hours
            $warnings[] = 'Session timeout is quite long, consider reducing it';
        }
    }

    protected function checkCorsConfiguration(array &$issues, array &$warnings): void
    {
        $this->info('Checking CORS configuration...');

        $corsConfig = config('cors');

        if (in_array('*', $corsConfig['allowed_origins'])) {
            $issues[] = 'CORS allows all origins (*), this is insecure';
        }

        if (in_array('*', $corsConfig['allowed_headers'])) {
            $warnings[] = 'CORS allows all headers (*), consider being more specific';
        }
    }

    protected function displayResults(array $issues, array $warnings): void
    {
        $this->newLine();

        if (empty($issues) && empty($warnings)) {
            $this->info('✅ Security audit completed successfully! No issues found.');

            return;
        }

        if (! empty($issues)) {
            $this->error('🚨 Security Issues Found:');
            foreach ($issues as $issue) {
                $this->error("  • {$issue}");
            }
            $this->newLine();
        }

        if (! empty($warnings)) {
            $this->warn('⚠️  Security Warnings:');
            foreach ($warnings as $warning) {
                $this->warn("  • {$warning}");
            }
            $this->newLine();
        }

        $this->info('Security audit completed.');
        $this->info('Issues: ' . count($issues) . ', Warnings: ' . count($warnings));

        if ($this->option('fix')) {
            $this->info('Auto-fix functionality not implemented yet.');
        }
    }
}
