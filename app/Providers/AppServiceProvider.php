<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\CacheService::class);
        $this->app->singleton(\App\Services\DistributedSessionHandler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->registerObservers();
    }

    /**
     * Register model observers.
     */
    protected function registerObservers(): void
    {
        \App\Models\Review::observe(\App\Observers\ReviewObserver::class);

        // Register audit observer for all models
        \App\Models\User::observe(\App\Observers\AuditObserver::class);
        \App\Models\Job::observe(\App\Observers\AuditObserver::class);
        \App\Models\Skill::observe(\App\Observers\AuditObserver::class);
        \App\Models\Message::observe(\App\Observers\AuditObserver::class);
        \App\Models\Payment::observe(\App\Observers\AuditObserver::class);
        \App\Models\Review::observe(\App\Observers\AuditObserver::class);
        \App\Models\GdprRequest::observe(\App\Observers\AuditObserver::class);
        \App\Models\UserConsent::observe(\App\Observers\AuditObserver::class);
        \App\Models\SecurityIncident::observe(\App\Observers\AuditObserver::class);
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });
    }
}
