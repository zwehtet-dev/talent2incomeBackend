<?php

namespace App\Jobs;

use App\Models\GeneratedReport;
use App\Models\ScheduledReport;
use App\Services\AnalyticsService;
use App\Services\ReportGenerationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateReport implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800; // 30 minutes
    public int $backoff = 300; // 5 minutes

    public function __construct(
        public ScheduledReport $scheduledReport,
        public Carbon $startDate,
        public Carbon $endDate,
        public array $options = []
    ) {
        $this->onQueue('reports');
    }

    public function handle(
        ReportGenerationService $reportService,
        AnalyticsService $analyticsService
    ): void {
        try {
            Log::info('Starting report generation', [
                'scheduled_report_id' => $this->scheduledReport->id,
                'report_type' => $this->scheduledReport->report_type,
                'period' => $this->startDate->format('Y-m-d') . ' to ' . $this->endDate->format('Y-m-d'),
            ]);

            // Create generated report record
            $generatedReport = GeneratedReport::create([
                'scheduled_report_id' => $this->scheduledReport->id,
                'name' => $this->generateReportName(),
                'report_type' => $this->scheduledReport->report_type,
                'parameters' => array_merge($this->scheduledReport->parameters, [
                    'start_date' => $this->startDate->toDateString(),
                    'end_date' => $this->endDate->toDateString(),
                ]),
                'status' => 'generating',
                'progress' => 0,
                'started_at' => now(),
            ]);

            $this->updateProgress($generatedReport, 5, 'Initializing report generation');

            // Generate report data based on type
            $reportData = $this->generateReportData($generatedReport, $reportService, $analyticsService);

            $this->updateProgress($generatedReport, 80, 'Processing report data');

            // Save report data
            $filePath = $this->saveReportData($generatedReport, $reportData);

            $this->updateProgress($generatedReport, 90, 'Finalizing report');

            // Update generated report with results
            $generatedReport->update([
                'status' => 'completed',
                'progress' => 100,
                'data' => $reportData,
                'file_path' => $filePath,
                'file_size' => Storage::size($filePath),
                'completed_at' => now(),
            ]);

            $this->updateProgress($generatedReport, 100, 'Report generation completed');

            // Send email notification if configured
            if ($this->scheduledReport->email_recipients) {
                $this->sendReportNotification($generatedReport);
            }

            Log::info('Report generation completed', [
                'generated_report_id' => $generatedReport->id,
                'file_size' => $generatedReport->file_size,
                'duration' => $generatedReport->started_at->diffInSeconds($generatedReport->completed_at),
            ]);

        } catch (\Exception $e) {
            if (isset($generatedReport)) {
                $generatedReport->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);
            }

            Log::error('Report generation failed', [
                'scheduled_report_id' => $this->scheduledReport->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Report generation job failed', [
            'scheduled_report_id' => $this->scheduledReport->id,
            'error' => $exception->getMessage(),
        ]);

        // Notify administrators about the failure
        $admins = \App\Models\User::where('is_admin', true)->get();
        foreach ($admins as $admin) {
            SendEmailNotification::dispatch(
                $admin,
                'report_generation_failed',
                [
                    'scheduled_report' => $this->scheduledReport,
                    'error' => $exception->getMessage(),
                    'attempts' => $this->attempts(),
                ]
            );
        }
    }

    protected function generateReportData(
        GeneratedReport $generatedReport,
        ReportGenerationService $reportService,
        AnalyticsService $analyticsService
    ): array {
        $reportType = $this->scheduledReport->report_type;
        $parameters = $generatedReport->parameters;

        switch ($reportType) {
            case 'revenue_analytics':
                return $this->generateRevenueReport($generatedReport, $analyticsService);
            case 'user_engagement':
                return $this->generateEngagementReport($generatedReport, $analyticsService);
            case 'system_performance':
                return $this->generatePerformanceReport($generatedReport, $analyticsService);
            case 'cohort_analysis':
                return $this->generateCohortReport($generatedReport, $analyticsService);
            case 'comprehensive':
                return $this->generateComprehensiveReport($generatedReport, $analyticsService);
            case 'custom':
                return $this->generateCustomReport($generatedReport, $reportService, $parameters);
            default:
                throw new \InvalidArgumentException("Unknown report type: {$reportType}");
        }
    }

    protected function generateRevenueReport(GeneratedReport $generatedReport, AnalyticsService $analyticsService): array
    {
        $this->updateProgress($generatedReport, 20, 'Calculating revenue metrics');

        $data = [
            'report_type' => 'revenue_analytics',
            'period' => [
                'start' => $this->startDate->toDateString(),
                'end' => $this->endDate->toDateString(),
            ],
            'summary' => [],
            'daily_breakdown' => [],
            'category_breakdown' => [],
            'trends' => [],
        ];

        // Calculate daily revenue
        $currentDate = $this->startDate->copy();
        $totalDays = $this->startDate->diffInDays($this->endDate) + 1;
        $processedDays = 0;

        while ($currentDate->lte($this->endDate)) {
            $dailyRevenue = $analyticsService->calculateRevenueAnalytics($currentDate);

            $data['daily_breakdown'][] = [
                'date' => $currentDate->toDateString(),
                'total_revenue' => $dailyRevenue->total_revenue,
                'platform_fees' => $dailyRevenue->platform_fees,
                'transaction_count' => $dailyRevenue->transaction_count,
                'average_transaction' => $dailyRevenue->average_transaction_value,
            ];

            $processedDays++;
            $progress = 20 + (($processedDays / $totalDays) * 40);
            $this->updateProgress($generatedReport, $progress, "Processing day {$processedDays} of {$totalDays}");

            $currentDate->addDay();
        }

        $this->updateProgress($generatedReport, 65, 'Calculating summary metrics');

        // Calculate summary
        $data['summary'] = [
            'total_revenue' => collect($data['daily_breakdown'])->sum('total_revenue'),
            'total_platform_fees' => collect($data['daily_breakdown'])->sum('platform_fees'),
            'total_transactions' => collect($data['daily_breakdown'])->sum('transaction_count'),
            'average_daily_revenue' => collect($data['daily_breakdown'])->avg('total_revenue'),
            'peak_day' => collect($data['daily_breakdown'])->sortByDesc('total_revenue')->first(),
        ];

        return $data;
    }

    protected function generateEngagementReport(GeneratedReport $generatedReport, AnalyticsService $analyticsService): array
    {
        $this->updateProgress($generatedReport, 20, 'Analyzing user engagement');

        $data = [
            'report_type' => 'user_engagement',
            'period' => [
                'start' => $this->startDate->toDateString(),
                'end' => $this->endDate->toDateString(),
            ],
            'summary' => [],
            'daily_metrics' => [],
            'user_segments' => [],
        ];

        // Process daily engagement metrics
        $currentDate = $this->startDate->copy();
        $totalDays = $this->startDate->diffInDays($this->endDate) + 1;
        $processedDays = 0;

        while ($currentDate->lte($this->endDate)) {
            $engagement = $analyticsService->calculateUserEngagementAnalytics($currentDate);

            $data['daily_metrics'][] = [
                'date' => $currentDate->toDateString(),
                'daily_active_users' => $engagement->daily_active_users,
                'new_registrations' => $engagement->total_new_registrations,
                'job_posts' => $engagement->total_job_posts,
                'applications' => $engagement->total_applications,
                'messages_sent' => $engagement->total_messages_sent,
            ];

            $processedDays++;
            $progress = 20 + (($processedDays / $totalDays) * 50);
            $this->updateProgress($generatedReport, $progress, "Processing engagement day {$processedDays}");

            $currentDate->addDay();
        }

        $this->updateProgress($generatedReport, 75, 'Calculating engagement summary');

        $data['summary'] = [
            'total_active_users' => collect($data['daily_metrics'])->max('daily_active_users'),
            'total_new_users' => collect($data['daily_metrics'])->sum('new_registrations'),
            'total_job_posts' => collect($data['daily_metrics'])->sum('job_posts'),
            'total_applications' => collect($data['daily_metrics'])->sum('applications'),
            'average_daily_activity' => collect($data['daily_metrics'])->avg('daily_active_users'),
        ];

        return $data;
    }

    protected function generatePerformanceReport(GeneratedReport $generatedReport, AnalyticsService $analyticsService): array
    {
        $this->updateProgress($generatedReport, 30, 'Collecting system performance metrics');

        return [
            'report_type' => 'system_performance',
            'period' => [
                'start' => $this->startDate->toDateString(),
                'end' => $this->endDate->toDateString(),
            ],
            'system_health' => $analyticsService->getSystemHealthMetrics($this->startDate, $this->endDate),
            'performance_trends' => $analyticsService->getPerformanceTrends($this->startDate, $this->endDate),
            'alerts' => $analyticsService->getSystemAlerts($this->startDate, $this->endDate),
        ];
    }

    protected function generateCohortReport(GeneratedReport $generatedReport, AnalyticsService $analyticsService): array
    {
        $this->updateProgress($generatedReport, 30, 'Analyzing user cohorts');

        $cohortData = [];
        $startMonth = $this->startDate->copy()->startOfMonth();
        $endMonth = $this->endDate->copy()->startOfMonth();

        $currentMonth = $startMonth->copy();
        while ($currentMonth->lte($endMonth)) {
            $periodNumber = $currentMonth->diffInMonths($endMonth);
            $cohort = $analyticsService->calculateCohortAnalytics($currentMonth, $periodNumber);

            $cohortData[] = [
                'cohort_month' => $currentMonth->format('Y-m'),
                'initial_users' => $cohort->cohort_size,
                'retention_rate' => $cohort->retention_rate,
                'active_users' => $cohort->active_users,
            ];

            $currentMonth->addMonth();
        }

        return [
            'report_type' => 'cohort_analysis',
            'period' => [
                'start' => $this->startDate->toDateString(),
                'end' => $this->endDate->toDateString(),
            ],
            'cohorts' => $cohortData,
            'summary' => [
                'average_retention' => collect($cohortData)->avg('retention_rate'),
                'best_cohort' => collect($cohortData)->sortByDesc('retention_rate')->first(),
                'total_cohorts' => count($cohortData),
            ],
        ];
    }

    protected function generateComprehensiveReport(GeneratedReport $generatedReport, AnalyticsService $analyticsService): array
    {
        $this->updateProgress($generatedReport, 10, 'Starting comprehensive analysis');

        $data = [
            'report_type' => 'comprehensive',
            'period' => [
                'start' => $this->startDate->toDateString(),
                'end' => $this->endDate->toDateString(),
            ],
        ];

        // Generate all report types
        $this->updateProgress($generatedReport, 20, 'Generating revenue data');
        $data['revenue'] = $this->generateRevenueReport($generatedReport, $analyticsService);

        $this->updateProgress($generatedReport, 40, 'Generating engagement data');
        $data['engagement'] = $this->generateEngagementReport($generatedReport, $analyticsService);

        $this->updateProgress($generatedReport, 60, 'Generating performance data');
        $data['performance'] = $this->generatePerformanceReport($generatedReport, $analyticsService);

        $this->updateProgress($generatedReport, 75, 'Generating cohort data');
        $data['cohorts'] = $this->generateCohortReport($generatedReport, $analyticsService);

        return $data;
    }

    protected function generateCustomReport(GeneratedReport $generatedReport, ReportGenerationService $reportService, array $parameters): array
    {
        $this->updateProgress($generatedReport, 30, 'Processing custom report parameters');

        return $reportService->generateCustomReport(
            $parameters,
            $this->startDate,
            $this->endDate,
            function ($progress, $message) use ($generatedReport) {
                $this->updateProgress($generatedReport, 30 + ($progress * 0.5), $message);
            }
        );
    }

    protected function saveReportData(GeneratedReport $generatedReport, array $data): string
    {
        $fileName = sprintf(
            'reports/%s/%s_%s.json',
            $this->scheduledReport->report_type,
            $generatedReport->id,
            now()->format('Y-m-d_H-i-s')
        );

        Storage::put($fileName, json_encode($data, JSON_PRETTY_PRINT));

        return $fileName;
    }

    protected function updateProgress(GeneratedReport $generatedReport, float $progress, string $message): void
    {
        $generatedReport->update([
            'progress' => min(100, max(0, $progress)),
            'status_message' => $message,
        ]);

        // Cache progress for real-time updates
        Cache::put(
            "report_progress_{$generatedReport->id}",
            [
                'progress' => $progress,
                'message' => $message,
                'updated_at' => now()->toISOString(),
            ],
            300 // 5 minutes
        );

        Log::debug('Report progress updated', [
            'report_id' => $generatedReport->id,
            'progress' => $progress,
            'message' => $message,
        ]);
    }

    protected function generateReportName(): string
    {
        $type = str_replace('_', ' ', $this->scheduledReport->report_type);
        $period = $this->startDate->format('M j') . ' - ' . $this->endDate->format('M j, Y');

        return ucwords($type) . " Report ({$period})";
    }

    protected function sendReportNotification(GeneratedReport $generatedReport): void
    {
        $recipients = $this->scheduledReport->email_recipients;

        foreach ($recipients as $email) {
            SendEmailNotification::dispatch(
                (object) ['email' => $email],
                'analytics_report',
                [
                    'report' => $generatedReport,
                    'scheduled_report' => $this->scheduledReport,
                ],
                "Your {$generatedReport->name} is Ready"
            );
        }
    }
}
