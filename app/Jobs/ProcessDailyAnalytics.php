<?php

namespace App\Jobs;

use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDailyAnalytics implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public Carbon $date;
    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    public function __construct(Carbon $date)
    {
        $this->date = $date;
        $this->onQueue('analytics');
    }

    public function handle(AnalyticsService $analyticsService): void
    {
        Log::info("Processing daily analytics for {$this->date->format('Y-m-d')}");

        try {
            // Calculate revenue analytics
            $revenueAnalytics = $analyticsService->calculateRevenueAnalytics($this->date);
            Log::info("Revenue analytics processed: \${$revenueAnalytics->total_revenue}");

            // Calculate user engagement analytics
            $engagementAnalytics = $analyticsService->calculateUserEngagementAnalytics($this->date);
            Log::info("Engagement analytics processed: {$engagementAnalytics->daily_active_users} DAU");

            // Calculate cohort analytics if it's the first day of the month
            if ($this->date->day === 1) {
                $cohortMonth = $this->date->copy()->startOfMonth();
                $periodNumber = $cohortMonth->diffInMonths(now()->startOfMonth());

                if ($periodNumber >= 0) {
                    $cohortAnalytics = $analyticsService->calculateCohortAnalytics($cohortMonth, $periodNumber);
                    Log::info("Cohort analytics processed: {$cohortAnalytics->retention_rate}% retention");
                }
            }

            Log::info("Daily analytics processing completed for {$this->date->format('Y-m-d')}");

        } catch (\Exception $e) {
            Log::error("Failed to process daily analytics for {$this->date->format('Y-m-d')}: {$e->getMessage()}");

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Daily analytics job failed for {$this->date->format('Y-m-d')}: {$exception->getMessage()}");
    }
}
