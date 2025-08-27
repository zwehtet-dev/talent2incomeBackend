<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PasswordHashServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Password hashing is configured via config/hashing.php
        // This provider is available for future enhancements
    }
}
