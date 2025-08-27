<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsRequest;
use App\Http\Requests\Analytics\CreateScheduledReportRequest;
use App\Models\GeneratedReport;
use App\Models\ScheduledReport;
use App\Services\AnalyticsService;
use App\Services\ReportGenerationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;
    protected ReportGenerationService $reportService;

    public function __construct(
        AnalyticsService $analyticsService,
        ReportGenerationService $reportService
    ) {
        $this->analyticsService = $analyticsService;
        $this->reportService = $reportService;
    }

    /**
     * Get comprehensive dashboard data
     */
    public function dashboard(AnalyticsRequest $request): JsonResponse
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $data = $this->analyticsService->getDashboardData($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get revenue analytics and trends
     */
    public function revenue(AnalyticsRequest $request): JsonResponse
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $trends = $this->analyticsService->getRevenueTrends($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Get user engagement analytics
     */
    public function engagement(AnalyticsRequest $request): JsonResponse
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $trends = $this->analyticsService->getUserEngagementTrends($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $trends,
        ]);
    }

    /**
     * Get cohort analysis
     */
    public function cohorts(Request $request): JsonResponse
    {
        $months = $request->input('months', 12);
        $analysis = $this->analyticsService->getCohortAnalysis($months);

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * Get system health indicators
     */
    public function systemHealth(): JsonResponse
    {
        $health = $this->analyticsService->getSystemHealthIndicators();

        return response()->json([
            'success' => true,
            'data' => $health,
        ]);
    }

    /**
     * Generate a custom report
     */
    public function generateReport(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:daily,weekly,monthly,custom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'metrics' => 'array',
            'metrics.*' => 'string|in:revenue_analytics,user_engagement,cohort_analysis,system_performance,key_metrics,trends,forecasting',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $metrics = $request->input('metrics', []);

        $report = $this->reportService->generateReport(
            $request->type,
            $startDate,
            $endDate,
            $metrics
        );

        return response()->json([
            'success' => true,
            'data' => [
                'report_id' => $report->id,
                'name' => $report->name,
                'type' => $report->type,
                'report_date' => $report->report_date,
                'file_path' => $report->file_path,
                'generated_at' => $report->generated_at,
                'data' => $report->data,
            ],
        ]);
    }

    /**
     * Get list of generated reports
     */
    public function reports(Request $request): JsonResponse
    {
        $query = GeneratedReport::query();

        if ($request->has('type')) {
            $query->forType($request->type);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $query->forDateRange($startDate, $endDate);
        }

        $reports = $query->orderBy('generated_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Get a specific report
     */
    public function getReport(GeneratedReport $report): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Create a scheduled report
     */
    public function createScheduledReport(CreateScheduledReportRequest $request): JsonResponse
    {
        $scheduledReport = $this->reportService->createScheduledReport($request->validated());

        return response()->json([
            'success' => true,
            'data' => $scheduledReport,
        ], 201);
    }

    /**
     * Get list of scheduled reports
     */
    public function scheduledReports(): JsonResponse
    {
        $reports = ScheduledReport::with('generatedReports')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Update a scheduled report
     */
    public function updateScheduledReport(Request $request, ScheduledReport $scheduledReport): JsonResponse
    {
        $request->validate([
            'name' => 'string|max:255',
            'recipients' => 'array',
            'recipients.*' => 'email',
            'metrics' => 'array',
            'frequency' => 'string|in:daily,weekly,monthly',
            'is_active' => 'boolean',
        ]);

        $scheduledReport->update($request->only([
            'name', 'recipients', 'metrics', 'frequency', 'is_active',
        ]));

        return response()->json([
            'success' => true,
            'data' => $scheduledReport,
        ]);
    }

    /**
     * Delete a scheduled report
     */
    public function deleteScheduledReport(ScheduledReport $scheduledReport): JsonResponse
    {
        $scheduledReport->delete();

        return response()->json([
            'success' => true,
            'message' => 'Scheduled report deleted successfully',
        ]);
    }

    /**
     * Manually trigger scheduled reports processing
     */
    public function processScheduledReports(): JsonResponse
    {
        $results = $this->reportService->processScheduledReports();

        return response()->json([
            'success' => true,
            'data' => [
                'processed_count' => count($results),
                'results' => $results,
            ],
        ]);
    }

    /**
     * Record system performance metrics
     */
    public function recordPerformanceMetrics(Request $request): JsonResponse
    {
        $request->validate([
            'average_response_time' => 'numeric|min:0',
            'total_requests' => 'integer|min:0',
            'error_count' => 'integer|min:0',
            'error_rate' => 'numeric|min:0|max:100',
            'cpu_usage' => 'numeric|min:0|max:100',
            'memory_usage' => 'numeric|min:0|max:100',
            'disk_usage' => 'numeric|min:0|max:100',
            'active_connections' => 'integer|min:0',
        ]);

        $metrics = $this->analyticsService->recordSystemPerformanceMetrics($request->all());

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Calculate analytics for a specific date (admin only)
     */
    public function calculateAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'types' => 'array',
            'types.*' => 'string|in:revenue,engagement,cohort',
        ]);

        $date = Carbon::parse($request->date);
        $types = $request->input('types', ['revenue', 'engagement']);
        $results = [];

        if (in_array('revenue', $types)) {
            $results['revenue'] = $this->analyticsService->calculateRevenueAnalytics($date);
        }

        if (in_array('engagement', $types)) {
            $results['engagement'] = $this->analyticsService->calculateUserEngagementAnalytics($date);
        }

        if (in_array('cohort', $types)) {
            // Calculate cohort for the month of the given date
            $cohortMonth = $date->copy()->startOfMonth();
            $periodNumber = $cohortMonth->diffInMonths(now()->startOfMonth());
            $results['cohort'] = $this->analyticsService->calculateCohortAnalytics($cohortMonth, $periodNumber);
        }

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }
}
