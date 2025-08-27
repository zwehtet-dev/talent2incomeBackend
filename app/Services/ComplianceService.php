<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SecurityIncident;
use App\Models\User;
use App\Models\UserConsent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComplianceService
{
    /**
     * Data retention periods in days.
     */
    public const RETENTION_PERIODS = [
        'audit_logs' => 2555, // 7 years
        'sensitive_audit_logs' => 3650, // 10 years
        'user_data' => 2555, // 7 years after account deletion
        'payment_data' => 2555, // 7 years
        'message_data' => 1095, // 3 years
        'session_data' => 30, // 30 days
        'security_incidents' => 2555, // 7 years
    ];

    /**
     * Run data retention cleanup.
     */
    public function runDataRetentionCleanup(): array
    {
        $results = [];

        // Clean up audit logs
        $results['audit_logs'] = $this->cleanupAuditLogs();

        // Clean up old sessions
        $results['sessions'] = $this->cleanupSessions();

        // Clean up old messages
        $results['messages'] = $this->cleanupMessages();

        // Clean up resolved security incidents
        $results['security_incidents'] = $this->cleanupSecurityIncidents();

        // Clean up expired consents
        $results['expired_consents'] = $this->cleanupExpiredConsents();

        return $results;
    }

    /**
     * Generate compliance report.
     */
    public function generateComplianceReport(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'gdpr_requests' => $this->getGdprRequestsReport($startDate, $endDate),
            'consent_management' => $this->getConsentReport($startDate, $endDate),
            'security_incidents' => $this->getSecurityIncidentsReport($startDate, $endDate),
            'audit_activity' => $this->getAuditActivityReport($startDate, $endDate),
            'data_retention' => $this->getDataRetentionReport(),
        ];
    }

    /**
     * Check compliance status.
     */
    public function checkComplianceStatus(): array
    {
        return [
            'gdpr_compliance' => $this->checkGdprCompliance(),
            'audit_compliance' => $this->checkAuditCompliance(),
            'security_compliance' => $this->checkSecurityCompliance(),
            'data_retention_compliance' => $this->checkDataRetentionCompliance(),
        ];
    }

    /**
     * Get data that can be cleaned up.
     */
    public function getCleanupCandidates(): array
    {
        $candidates = [];

        foreach (self::RETENTION_PERIODS as $dataType => $retentionDays) {
            $cutoffDate = now()->subDays($retentionDays);

            switch ($dataType) {
                case 'audit_logs':
                    $candidates[$dataType] = AuditLog::where('created_at', '<', $cutoffDate)
                        ->where('is_sensitive', false)
                        ->count();

                    break;

                case 'sessions':
                    $candidates[$dataType] = DB::table('sessions')
                        ->where('last_activity', '<', $cutoffDate->timestamp)
                        ->count();

                    break;

                case 'messages':
                    $candidates[$dataType] = DB::table('messages')
                        ->where('created_at', '<', $cutoffDate)
                        ->count();

                    break;
            }
        }

        return $candidates;
    }

    /**
     * Clean up old audit logs.
     */
    private function cleanupAuditLogs(): int
    {
        $cutoffDate = now()->subDays(self::RETENTION_PERIODS['audit_logs']);

        return AuditLog::where('created_at', '<', $cutoffDate)
            ->where('is_sensitive', false)
            ->delete();
    }

    /**
     * Clean up old sessions.
     */
    private function cleanupSessions(): int
    {
        $cutoffDate = now()->subDays(self::RETENTION_PERIODS['session_data']);

        return DB::table('sessions')
            ->where('last_activity', '<', $cutoffDate->timestamp)
            ->delete();
    }

    /**
     * Clean up old messages.
     */
    private function cleanupMessages(): int
    {
        $cutoffDate = now()->subDays(self::RETENTION_PERIODS['message_data']);

        return DB::table('messages')
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Clean up resolved security incidents.
     */
    private function cleanupSecurityIncidents(): int
    {
        $cutoffDate = now()->subDays(self::RETENTION_PERIODS['security_incidents']);

        return SecurityIncident::where('created_at', '<', $cutoffDate)
            ->whereIn('status', [
                SecurityIncident::STATUS_RESOLVED,
                SecurityIncident::STATUS_FALSE_POSITIVE,
            ])
            ->delete();
    }

    /**
     * Clean up expired consents.
     */
    private function cleanupExpiredConsents(): int
    {
        return UserConsent::expired()->delete();
    }

    /**
     * Get GDPR requests report.
     */
    private function getGdprRequestsReport(Carbon $startDate, Carbon $endDate): array
    {
        $requests = DB::table('gdpr_requests')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'total_requests' => $requests->count(),
            'by_type' => $requests->groupBy('request_type')->map->count(),
            'by_status' => $requests->groupBy('status')->map->count(),
            'average_processing_time' => $this->calculateAverageProcessingTime($requests),
        ];
    }

    /**
     * Get consent management report.
     */
    private function getConsentReport(Carbon $startDate, Carbon $endDate): array
    {
        $consents = DB::table('user_consents')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'total_consents' => $consents->count(),
            'granted' => $consents->where('is_granted', true)->count(),
            'withdrawn' => $consents->where('is_granted', false)->count(),
            'by_type' => $consents->groupBy('consent_type')->map->count(),
            'compliance_rate' => $this->calculateConsentComplianceRate(),
        ];
    }

    /**
     * Get security incidents report.
     */
    private function getSecurityIncidentsReport(Carbon $startDate, Carbon $endDate): array
    {
        $incidents = DB::table('security_incidents')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'total_incidents' => $incidents->count(),
            'by_severity' => $incidents->groupBy('severity')->map->count(),
            'by_type' => $incidents->groupBy('incident_type')->map->count(),
            'by_status' => $incidents->groupBy('status')->map->count(),
            'average_resolution_time' => $this->calculateAverageResolutionTime($incidents),
        ];
    }

    /**
     * Get audit activity report.
     */
    private function getAuditActivityReport(Carbon $startDate, Carbon $endDate): array
    {
        $logs = DB::table('audit_logs')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'total_events' => $logs->count(),
            'by_event_type' => $logs->groupBy('event_type')->map->count(),
            'by_severity' => $logs->groupBy('severity')->map->count(),
            'sensitive_events' => $logs->where('is_sensitive', true)->count(),
            'unique_users' => $logs->whereNotNull('user_id')->unique('user_id')->count(),
        ];
    }

    /**
     * Get data retention report.
     */
    private function getDataRetentionReport(): array
    {
        return [
            'retention_policies' => self::RETENTION_PERIODS,
            'data_volumes' => [
                'audit_logs' => DB::table('audit_logs')->count(),
                'user_consents' => DB::table('user_consents')->count(),
                'security_incidents' => DB::table('security_incidents')->count(),
                'gdpr_requests' => DB::table('gdpr_requests')->count(),
            ],
            'cleanup_candidates' => $this->getCleanupCandidates(),
        ];
    }

    /**
     * Calculate average processing time for GDPR requests.
     * @param mixed $requests
     */
    private function calculateAverageProcessingTime($requests): ?float
    {
        $completedRequests = $requests->whereNotNull('completed_at');

        if ($completedRequests->isEmpty()) {
            return null;
        }

        $totalTime = $completedRequests->sum(function ($request) {
            return Carbon::parse($request->completed_at)
                ->diffInHours(Carbon::parse($request->created_at));
        });

        return round($totalTime / $completedRequests->count(), 2);
    }

    /**
     * Calculate consent compliance rate.
     */
    private function calculateConsentComplianceRate(): float
    {
        $totalUsers = User::count();
        $compliantUsers = User::whereHas('consents', function ($query) {
            $query->where('consent_type', UserConsent::TYPE_PRIVACY_POLICY)
                ->where('is_granted', true)
                ->whereNull('withdrawn_at');
        })->count();

        return $totalUsers > 0 ? round(($compliantUsers / $totalUsers) * 100, 2) : 0;
    }

    /**
     * Calculate average resolution time for security incidents.
     * @param mixed $incidents
     */
    private function calculateAverageResolutionTime($incidents): ?float
    {
        $resolvedIncidents = $incidents->whereNotNull('resolved_at');

        if ($resolvedIncidents->isEmpty()) {
            return null;
        }

        $totalTime = $resolvedIncidents->sum(function ($incident) {
            return Carbon::parse($incident->resolved_at)
                ->diffInHours(Carbon::parse($incident->first_detected_at));
        });

        return round($totalTime / $resolvedIncidents->count(), 2);
    }

    /**
     * Check GDPR compliance.
     */
    private function checkGdprCompliance(): array
    {
        $pendingRequests = DB::table('gdpr_requests')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        return [
            'status' => $pendingRequests === 0 ? 'compliant' : 'non_compliant',
            'pending_requests_over_30_days' => $pendingRequests,
            'issues' => $pendingRequests > 0 ? ['Pending GDPR requests over 30 days old'] : [],
        ];
    }

    /**
     * Check audit compliance.
     */
    private function checkAuditCompliance(): array
    {
        $recentLogs = AuditLog::where('created_at', '>=', now()->subDays(7))->count();
        $integrityIssues = AuditLog::whereNull('hash')->count();

        return [
            'status' => $integrityIssues === 0 ? 'compliant' : 'non_compliant',
            'recent_audit_activity' => $recentLogs,
            'integrity_issues' => $integrityIssues,
            'issues' => $integrityIssues > 0 ? ['Audit logs with missing integrity hashes'] : [],
        ];
    }

    /**
     * Check security compliance.
     */
    private function checkSecurityCompliance(): array
    {
        $openCriticalIncidents = SecurityIncident::where('severity', 'critical')
            ->where('status', 'open')
            ->count();

        return [
            'status' => $openCriticalIncidents === 0 ? 'compliant' : 'non_compliant',
            'open_critical_incidents' => $openCriticalIncidents,
            'issues' => $openCriticalIncidents > 0 ? ['Open critical security incidents'] : [],
        ];
    }

    /**
     * Check data retention compliance.
     */
    private function checkDataRetentionCompliance(): array
    {
        $cleanupCandidates = $this->getCleanupCandidates();
        $totalCandidates = array_sum($cleanupCandidates);

        return [
            'status' => $totalCandidates === 0 ? 'compliant' : 'attention_needed',
            'cleanup_candidates' => $cleanupCandidates,
            'total_records_for_cleanup' => $totalCandidates,
            'issues' => $totalCandidates > 0 ? ['Data exceeding retention periods'] : [],
        ];
    }
}
