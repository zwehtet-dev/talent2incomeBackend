<?php

namespace App\Console\Commands;

use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessAnalytics extends Command
{
    protected $signature = 'analytics:process {--date= : Process analytics for specific date (Y-m-d)} {--days=1 : Number of days to process}';
    protected $description = 'Process and calculate analytics data for specified dates';

    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        parent::__construct();
        $this->analyticsService = $analyticsService;
    }

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : now()->subDay();
        $days = (int) $this->option('days');

        $this->info("Processing analytics for {$days} day(s) starting from {$date->format('Y-m-d')}");

        $processedDates = [];
        $errors = [];

        for ($i = 0; $i < $days; $i++) {
            $currentDate = $date->copy()->subDays($i);

            try {
                $this->info("Processing analytics for {$currentDate->format('Y-m-d')}...");

                // Process revenue analytics
                $revenueAnalytics = $this->analyticsService->calculateRevenueAnalytics($currentDate);
                $this->line("  ✓ Revenue analytics: \${$revenueAnalytics->total_revenue}");

                // Process user engagement analytics
                $engagementAnalytics = $this->analyticsService->calculateUserEngagementAnalytics($currentDate);
                $this->line("  ✓ Engagement analytics: {$engagementAnalytics->daily_active_users} DAU");

                // Process cohort analytics if it's the first day of the month
                if ($currentDate->day === 1) {
                    $cohortMonth = $currentDate->copy()->startOfMonth();
                    $periodNumber = $cohortMonth->diffInMonths(now()->startOfMonth());

                    if ($periodNumber >= 0) {
                        $cohortAnalytics = $this->analyticsService->calculateCohortAnalytics($cohortMonth, $periodNumber);
                        $this->line("  ✓ Cohort analytics: {$cohortAnalytics->retention_rate}% retention");
                    }
                }

                $processedDates[] = $currentDate->format('Y-m-d');

            } catch (\Exception $e) {
                $this->error("  ✗ Error processing {$currentDate->format('Y-m-d')}: {$e->getMessage()}");
                $errors[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->newLine();
        $this->info('Analytics processing completed!');
        $this->info('Processed dates: ' . implode(', ', $processedDates));

        if (! empty($errors)) {
            $this->error('Errors encountered:');
            foreach ($errors as $error) {
                $this->error("  {$error['date']}: {$error['error']}");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
