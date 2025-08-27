<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue Management Scheduling
Schedule::command('queue:health-check')->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/queue-health.log'));

Schedule::command('queue:schedule')->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/queue-scheduler.log'));

// Daily maintenance tasks
Schedule::command('queue:schedule --job=daily_cleanup')->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queue:schedule --job=search_maintenance')->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queue:schedule --job=rating_cache_update')->dailyAt('00:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queue:schedule --job=daily_analytics')->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();

// Weekly maintenance tasks
Schedule::command('queue:schedule --job=weekly_cleanup')->weeklyOn(0, '03:00') // Sunday at 3 AM
    ->withoutOverlapping()
    ->runInBackground();

// Monthly maintenance tasks
Schedule::command('queue:schedule --job=search_rebuild')->monthlyOn(1, '04:00') // 1st day at 4 AM
    ->withoutOverlapping()
    ->runInBackground();

// Periodic tasks
Schedule::command('queue:schedule --job=saved_search_notifications')->everySixHours()
    ->withoutOverlapping()
    ->runInBackground();

// Worker scaling and monitoring
Schedule::command('queue:workers scale')->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->when(function () {
        return config('background_jobs.monitoring.auto_scaling_enabled', false);
    });

// Cleanup failed jobs older than 7 days
Schedule::command('queue:flush')->weekly()
    ->withoutOverlapping()
    ->runInBackground();
