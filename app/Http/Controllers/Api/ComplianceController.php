<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\ComplianceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function __construct(
        private ComplianceService $complianceService,
        private AuditService $auditService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('admin');
    }

    /**
     * Get compliance status overview.
     */
    public function status(): JsonResponse
    {
        $status = $this->complianceService->checkComplianceStatus();

        return response()->json([
            'compliance_status' => $status,
            'last_updated' => now()->toISOString(),
        ]);
    }

    /**
     * Generate compliance report.
     */
    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $report = $this->complianceService->generateComplianceReport($startDate, $endDate);

        // Log the report generation
        $this->auditService->logAdmin('compliance_report_generated', null, [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        return response()->json($report);
    }

    /**
     * Run data retention cleanup.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $request->validate([
            'dry_run' => 'boolean',
        ]);

        $dryRun = $request->boolean('dry_run', false);

        if ($dryRun) {
            // Return what would be cleaned up without actually doing it
            $candidates = $this->complianceService->getCleanupCandidates();

            return response()->json([
                'message' => 'Dry run completed',
                'cleanup_candidates' => $candidates,
                'total_records' => array_sum($candidates),
            ]);
        }

        $results = $this->complianceService->runDataRetentionCleanup();

        // Log the cleanup
        $this->auditService->logAdmin('data_retention_cleanup', null, [
            'results' => $results,
            'total_cleaned' => array_sum($results),
        ]);

        return response()->json([
            'message' => 'Data retention cleanup completed',
            'results' => $results,
            'total_cleaned' => array_sum($results),
        ]);
    }

    /**
     * Get audit logs with filtering.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'string',
            'user_id' => 'integer|exists:users,id',
            'severity' => 'string|in:info,warning,error,critical',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'auditable_type' => 'string',
            'sensitive' => 'boolean',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $filters = $request->only([
            'event_type',
            'user_id',
            'severity',
            'start_date',
            'end_date',
            'auditable_type',
            'sensitive',
        ]);

        $perPage = $request->integer('per_page', 50);

        $logs = $this->auditService->getLogs($filters, $perPage);

        return response()->json($logs);
    }

    /**
     * Verify audit log integrity.
     */
    public function verifyAuditIntegrity(Request $request): JsonResponse
    {
        $request->validate([
            'log_ids' => 'array',
            'log_ids.*' => 'integer|exists:audit_logs,id',
        ]);

        $logIds = $request->input('log_ids', []);
        $results = [];

        if (empty($logIds)) {
            // Verify recent logs if no specific IDs provided
            $logs = \App\Models\AuditLog::orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
        } else {
            $logs = \App\Models\AuditLog::whereIn('id', $logIds)->get();
        }

        foreach ($logs as $log) {
            $results[] = [
                'id' => $log->id,
                'created_at' => $log->created_at,
                'event_type' => $log->event_type,
                'integrity_valid' => $this->auditService->verifyIntegrity($log),
            ];
        }

        $validCount = collect($results)->where('integrity_valid', true)->count();
        $totalCount = count($results);

        return response()->json([
            'verification_results' => $results,
            'summary' => [
                'total_verified' => $totalCount,
                'valid_logs' => $validCount,
                'invalid_logs' => $totalCount - $validCount,
                'integrity_rate' => $totalCount > 0 ? round(($validCount / $totalCount) * 100, 2) : 0,
            ],
        ]);
    }

    /**
     * Get data retention policies.
     */
    public function retentionPolicies(): JsonResponse
    {
        return response()->json([
            'retention_periods' => ComplianceService::RETENTION_PERIODS,
            'description' => 'Data retention periods in days',
        ]);
    }

    /**
     * Export compliance data.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:audit_logs,gdpr_requests,security_incidents',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'string|in:json,csv',
        ]);

        $type = $request->input('type');
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $format = $request->input('format', 'json');

        // Log the export request
        $this->auditService->logAdmin('compliance_data_export', null, [
            'type' => $type,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'format' => $format,
        ]);

        // In a real implementation, you would generate and return the export file
        return response()->json([
            'message' => 'Export request queued',
            'type' => $type,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'format' => $format,
            'estimated_completion' => now()->addMinutes(5)->toISOString(),
        ]);
    }
}
