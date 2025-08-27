<?php

namespace App\Services;

use App\Mail\AnalyticsReport;
use App\Models\GeneratedReport;
use App\Models\ScheduledReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReportGenerationService
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Generate a comprehensive analytics report
     */
    public function generateReport(string $type, Carbon $startDate, Carbon $endDate, array $metrics = []): GeneratedReport
    {
        $reportData = $this->collectReportData($type, $startDate, $endDate, $metrics);

        $report = GeneratedReport::create([
            'name' => $this->generateReportName($type, $startDate, $endDate),
            'type' => $type,
            'report_date' => $endDate,
            'data' => $reportData,
            'generated_at' => now(),
        ]);

        // Generate and save PDF/CSV file if needed
        $filePath = $this->generateReportFile($report, $reportData);
        if ($filePath) {
            $report->update(['file_path' => $filePath]);
        }

        return $report;
    }

    /**
     * Generate daily report
     */
    public function generateDailyReport(Carbon $date = null): GeneratedReport
    {
        $date ??= now()->subDay();

        return $this->generateReport('daily', $date, $date, [
            'revenue_analytics',
            'user_engagement',
            'system_performance',
            'key_metrics',
        ]);
    }

    /**
     * Generate weekly report
     */
    public function generateWeeklyReport(Carbon $endDate = null): GeneratedReport
    {
        $endDate ??= now()->subDay();
        $startDate = $endDate->copy()->subDays(6);

        return $this->generateReport('weekly', $startDate, $endDate, [
            'revenue_analytics',
            'user_engagement',
            'cohort_analysis',
            'system_performance',
            'trends',
        ]);
    }

    /**
     * Generate monthly report
     */
    public function generateMonthlyReport(Carbon $month = null): GeneratedReport
    {
        $month ??= now()->subMonth();
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();

        return $this->generateReport('monthly', $startDate, $endDate, [
            'revenue_analytics',
            'user_engagement',
            'cohort_analysis',
            'system_performance',
            'comprehensive_trends',
            'forecasting',
        ]);
    }

    /**
     * Process scheduled reports
     */
    public function processScheduledReports(): array
    {
        $processedReports = [];

        $dueReports = ScheduledReport::active()->due()->get();

        foreach ($dueReports as $scheduledReport) {
            try {
                $report = $this->generateScheduledReport($scheduledReport);
                $this->sendReportToRecipients($report, $scheduledReport);
                $this->updateScheduledReportNextSend($scheduledReport);

                $processedReports[] = [
                    'scheduled_report_id' => $scheduledReport->id,
                    'generated_report_id' => $report->id,
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                $processedReports[] = [
                    'scheduled_report_id' => $scheduledReport->id,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $processedReports;
    }

    /**
     * Create a new scheduled report
     */
    public function createScheduledReport(array $data): ScheduledReport
    {
        $nextSendAt = $this->calculateNextSendTime($data['frequency']);

        return ScheduledReport::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'recipients' => $data['recipients'],
            'metrics' => $data['metrics'],
            'frequency' => $data['frequency'],
            'next_send_at' => $nextSendAt,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Collect data for report based on type and metrics
     */
    protected function collectReportData(string $type, Carbon $startDate, Carbon $endDate, array $metrics): array
    {
        $data = [
            'report_type' => $type,
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days' => $startDate->diffInDays($endDate) + 1,
            ],
            'generated_at' => now()->toISOString(),
        ];

        if (empty($metrics) || in_array('revenue_analytics', $metrics)) {
            $data['revenue'] = $this->analyticsService->getRevenueTrends($startDate, $endDate);
        }

        if (empty($metrics) || in_array('user_engagement', $metrics)) {
            $data['engagement'] = $this->analyticsService->getUserEngagementTrends($startDate, $endDate);
        }

        if (empty($metrics) || in_array('cohort_analysis', $metrics)) {
            $data['cohorts'] = $this->analyticsService->getCohortAnalysis();
        }

        if (empty($metrics) || in_array('system_performance', $metrics)) {
            $data['system_health'] = $this->analyticsService->getSystemHealthIndicators();
        }

        if (empty($metrics) || in_array('key_metrics', $metrics)) {
            $data['key_metrics'] = $this->analyticsService->getDashboardData($startDate, $endDate)['key_metrics'];
        }

        if (in_array('trends', $metrics) || in_array('comprehensive_trends', $metrics)) {
            $data['trends'] = $this->generateTrendAnalysis($startDate, $endDate);
        }

        if (in_array('forecasting', $metrics)) {
            $data['forecasting'] = $this->generateForecastingData($startDate, $endDate);
        }

        return $data;
    }

    /**
     * Generate trend analysis
     */
    protected function generateTrendAnalysis(Carbon $startDate, Carbon $endDate): array
    {
        $currentPeriod = $this->analyticsService->getRevenueTrends($startDate, $endDate);

        $periodLength = $startDate->diffInDays($endDate);
        $previousStart = $startDate->copy()->subDays($periodLength + 1);
        $previousEnd = $startDate->copy()->subDay();
        $previousPeriod = $this->analyticsService->getRevenueTrends($previousStart, $previousEnd);

        return [
            'revenue_growth' => $this->calculateGrowthRate(
                $currentPeriod['total_revenue'],
                $previousPeriod['total_revenue']
            ),
            'transaction_growth' => $this->calculateGrowthRate(
                $currentPeriod['total_transactions'],
                $previousPeriod['total_transactions']
            ),
            'user_growth' => $this->calculateUserGrowthRate($startDate, $endDate),
            'engagement_trends' => $this->calculateEngagementTrends($startDate, $endDate),
        ];
    }

    /**
     * Generate forecasting data
     */
    protected function generateForecastingData(Carbon $startDate, Carbon $endDate): array
    {
        // Simple linear regression for revenue forecasting
        $revenueData = $this->analyticsService->getRevenueTrends($startDate, $endDate);

        return [
            'next_month_revenue_forecast' => $this->forecastRevenue($revenueData),
            'user_growth_forecast' => $this->forecastUserGrowth($startDate, $endDate),
            'confidence_level' => 0.75, // Placeholder confidence level
        ];
    }

    /**
     * Generate scheduled report
     */
    protected function generateScheduledReport(ScheduledReport $scheduledReport): GeneratedReport
    {
        $endDate = now()->subDay();

        switch ($scheduledReport->frequency) {
            case 'daily':
                $startDate = $endDate;

                break;
            case 'weekly':
                $startDate = $endDate->copy()->subDays(6);

                break;
            case 'monthly':
                $startDate = $endDate->copy()->startOfMonth();

                break;
            default:
                $startDate = $endDate;
        }

        return $this->generateReport(
            $scheduledReport->type,
            $startDate,
            $endDate,
            $scheduledReport->metrics
        );
    }

    /**
     * Send report to recipients
     */
    protected function sendReportToRecipients(GeneratedReport $report, ScheduledReport $scheduledReport): void
    {
        foreach ($scheduledReport->recipients as $email) {
            Mail::to($email)->send(new AnalyticsReport($report, $scheduledReport));
        }
    }

    /**
     * Update next send time for scheduled report
     */
    protected function updateScheduledReportNextSend(ScheduledReport $scheduledReport): void
    {
        $nextSendAt = $this->calculateNextSendTime($scheduledReport->frequency);

        $scheduledReport->update([
            'last_sent_at' => now(),
            'next_send_at' => $nextSendAt,
        ]);
    }

    /**
     * Calculate next send time based on frequency
     */
    protected function calculateNextSendTime(string $frequency): Carbon
    {
        switch ($frequency) {
            case 'daily':
                return now()->addDay()->startOfDay()->addHours(8); // 8 AM next day
            case 'weekly':
                return now()->addWeek()->startOfWeek()->addHours(8); // 8 AM Monday
            case 'monthly':
                return now()->addMonth()->startOfMonth()->addHours(8); // 8 AM 1st of month
            default:
                return now()->addDay();
        }
    }

    /**
     * Generate report name
     */
    protected function generateReportName(string $type, Carbon $startDate, Carbon $endDate): string
    {
        if ($startDate->eq($endDate)) {
            return ucfirst($type) . ' Report - ' . $startDate->format('Y-m-d');
        }

        return ucfirst($type) . ' Report - ' . $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d');
    }

    /**
     * Generate report file (PDF/CSV)
     */
    protected function generateReportFile(GeneratedReport $report, array $data): ?string
    {
        // For now, just save as JSON file
        // In production, you might want to generate PDF or CSV
        $filename = 'reports/' . $report->type . '_' . $report->report_date->format('Y-m-d') . '_' . $report->id . '.json';

        Storage::put($filename, json_encode($data, JSON_PRETTY_PRINT));

        return $filename;
    }

    /**
     * Calculate growth rate between two values
     */
    protected function calculateGrowthRate(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (($current - $previous) / $previous) * 100;
    }

    /**
     * Calculate user growth rate
     */
    protected function calculateUserGrowthRate(Carbon $startDate, Carbon $endDate): float
    {
        $currentUsers = $this->analyticsService->getUserEngagementTrends($startDate, $endDate);

        $periodLength = $startDate->diffInDays($endDate);
        $previousStart = $startDate->copy()->subDays($periodLength + 1);
        $previousEnd = $startDate->copy()->subDay();
        $previousUsers = $this->analyticsService->getUserEngagementTrends($previousStart, $previousEnd);

        return $this->calculateGrowthRate(
            $currentUsers['total_new_registrations'],
            $previousUsers['total_new_registrations']
        );
    }

    /**
     * Calculate engagement trends
     */
    protected function calculateEngagementTrends(Carbon $startDate, Carbon $endDate): array
    {
        $engagement = $this->analyticsService->getUserEngagementTrends($startDate, $endDate);

        return [
            'jobs_posted_trend' => $engagement['total_jobs_posted'],
            'messages_sent_trend' => $engagement['total_messages_sent'],
            'reviews_created_trend' => $engagement['total_reviews_created'],
            'average_daily_active_users' => $engagement['average_daily_active_users'],
        ];
    }

    /**
     * Forecast revenue using simple linear regression
     */
    protected function forecastRevenue(array $revenueData): float
    {
        // Simple forecast based on current trend
        // In production, you might use more sophisticated algorithms
        $dailyData = $revenueData['daily_data'];

        if (count($dailyData) < 2) {
            return $revenueData['total_revenue'];
        }

        $recentData = array_slice($dailyData->toArray(), -7); // Last 7 days
        $averageDaily = collect($recentData)->avg('revenue');

        return $averageDaily * 30; // Forecast for next 30 days
    }

    /**
     * Forecast user growth
     */
    protected function forecastUserGrowth(Carbon $startDate, Carbon $endDate): int
    {
        $engagement = $this->analyticsService->getUserEngagementTrends($startDate, $endDate);
        $days = $startDate->diffInDays($endDate) + 1;

        $averageDailyRegistrations = $engagement['total_new_registrations'] / $days;

        return (int) ($averageDailyRegistrations * 30); // Forecast for next 30 days
    }
}
