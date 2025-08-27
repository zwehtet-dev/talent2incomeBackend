<?php

namespace App\Services;

use App\Models\SecurityIncident;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityIncidentService
{
    /**
     * Create a new security incident.
     */
    public function createIncident(
        string $incidentType,
        string $title,
        string $description,
        string $severity = SecurityIncident::SEVERITY_MEDIUM,
        array $metadata = []
    ): SecurityIncident {
        $request = request();

        $incident = SecurityIncident::create([
            'incident_type' => $incidentType,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'source_ip' => $request?->ip(),
            'target_endpoint' => $request?->fullUrl(),
            'http_method' => $request?->method(),
            'request_headers' => $this->sanitizeHeaders($request?->headers->all()),
            'request_payload' => $this->sanitizePayload($request?->all()),
            'user_agent' => $request?->userAgent(),
            'detection_method' => 'automated',
            'metadata' => $metadata,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        // Send alerts for high priority incidents
        if ($incident->requiresImmediateAttention()) {
            $this->sendSecurityAlert($incident);
        }

        return $incident;
    }

    /**
     * Report a brute force attack.
     */
    public function reportBruteForceAttack(
        string $email,
        string $ip,
        int $attemptCount = 1
    ): SecurityIncident {
        // Check if we already have an incident for this IP
        $existingIncident = SecurityIncident::fromIp($ip)
            ->type(SecurityIncident::TYPE_BRUTE_FORCE)
            ->where('status', SecurityIncident::STATUS_OPEN)
            ->first();

        if ($existingIncident) {
            $existingIncident->incrementAttackCount();

            return $existingIncident;
        }

        return $this->createIncident(
            SecurityIncident::TYPE_BRUTE_FORCE,
            "Brute force attack detected from {$ip}",
            "Multiple failed login attempts detected for email: {$email}",
            SecurityIncident::SEVERITY_HIGH,
            [
                'target_email' => $email,
                'attempt_count' => $attemptCount,
            ]
        );
    }

    /**
     * Report suspicious activity.
     */
    public function reportSuspiciousActivity(
        string $activity,
        User $user = null,
        string $severity = SecurityIncident::SEVERITY_MEDIUM
    ): SecurityIncident {
        return $this->createIncident(
            SecurityIncident::TYPE_SUSPICIOUS_ACTIVITY,
            'Suspicious activity detected',
            $activity,
            $severity,
            [
                'user_id' => $user?->id,
                'activity_type' => $activity,
            ]
        );
    }

    /**
     * Report potential SQL injection attempt.
     */
    public function reportSqlInjectionAttempt(
        string $payload,
        string $endpoint
    ): SecurityIncident {
        return $this->createIncident(
            SecurityIncident::TYPE_SQL_INJECTION,
            'SQL injection attempt detected',
            "Potential SQL injection payload detected in request to {$endpoint}",
            SecurityIncident::SEVERITY_HIGH,
            [
                'payload_sample' => substr($payload, 0, 500), // Limit payload size
                'endpoint' => $endpoint,
            ]
        );
    }

    /**
     * Report XSS attempt.
     */
    public function reportXssAttempt(
        string $payload,
        string $field
    ): SecurityIncident {
        return $this->createIncident(
            SecurityIncident::TYPE_XSS,
            'XSS attempt detected',
            "Potential XSS payload detected in field: {$field}",
            SecurityIncident::SEVERITY_HIGH,
            [
                'payload_sample' => substr($payload, 0, 500),
                'field' => $field,
            ]
        );
    }

    /**
     * Report unauthorized access attempt.
     */
    public function reportUnauthorizedAccess(
        string $resource,
        User $user = null
    ): SecurityIncident {
        return $this->createIncident(
            SecurityIncident::TYPE_UNAUTHORIZED_ACCESS,
            'Unauthorized access attempt',
            "Attempt to access protected resource: {$resource}",
            SecurityIncident::SEVERITY_HIGH,
            [
                'resource' => $resource,
                'user_id' => $user?->id,
            ]
        );
    }

    /**
     * Assign incident to admin.
     */
    public function assignIncident(SecurityIncident $incident, int $adminId): void
    {
        $incident->assignTo($adminId);

        // Log the assignment
        Log::info('Security incident assigned', [
            'incident_id' => $incident->id,
            'assigned_to' => $adminId,
        ]);
    }

    /**
     * Resolve incident.
     */
    public function resolveIncident(
        SecurityIncident $incident,
        string $summary,
        array $mitigationActions = []
    ): void {
        foreach ($mitigationActions as $action) {
            $incident->addMitigationAction($action);
        }

        $incident->markAsResolved($summary);

        Log::info('Security incident resolved', [
            'incident_id' => $incident->id,
            'resolution_summary' => $summary,
        ]);
    }

    /**
     * Get incident statistics.
     */
    public function getIncidentStatistics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $incidents = SecurityIncident::where('created_at', '>=', $startDate)->get();

        return [
            'total_incidents' => $incidents->count(),
            'by_severity' => $incidents->groupBy('severity')->map->count(),
            'by_type' => $incidents->groupBy('incident_type')->map->count(),
            'by_status' => $incidents->groupBy('status')->map->count(),
            'open_critical' => $incidents->where('severity', SecurityIncident::SEVERITY_CRITICAL)
                ->where('status', SecurityIncident::STATUS_OPEN)
                ->count(),
            'average_resolution_time' => $this->calculateAverageResolutionTime($incidents),
            'top_source_ips' => $this->getTopSourceIps($incidents),
        ];
    }

    /**
     * Get incidents requiring attention.
     */
    public function getIncidentsRequiringAttention(): \Illuminate\Database\Eloquent\Collection
    {
        return SecurityIncident::where(function ($query) {
            $query->where('severity', SecurityIncident::SEVERITY_CRITICAL)
                ->orWhere(function ($subQuery) {
                    $subQuery->where('severity', SecurityIncident::SEVERITY_HIGH)
                        ->where('created_at', '<', now()->subHours(4));
                });
        })
            ->where('status', SecurityIncident::STATUS_OPEN)
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Auto-resolve false positives.
     */
    public function autoResolveFalsePositives(): int
    {
        $count = 0;

        // Auto-resolve old low severity incidents with no activity
        $oldIncidents = SecurityIncident::where('severity', SecurityIncident::SEVERITY_LOW)
            ->where('status', SecurityIncident::STATUS_OPEN)
            ->where('created_at', '<', now()->subDays(7))
            ->where('attack_count', 1)
            ->get();

        foreach ($oldIncidents as $incident) {
            $incident->markAsFalsePositive('Auto-resolved: Low severity, single occurrence, no recent activity');
            $count++;
        }

        return $count;
    }

    /**
     * Send security alert.
     */
    private function sendSecurityAlert(SecurityIncident $incident): void
    {
        // In a real implementation, you would send emails to security team
        Log::critical('Security incident requires immediate attention', [
            'incident_id' => $incident->id,
            'type' => $incident->incident_type,
            'severity' => $incident->severity,
            'title' => $incident->title,
            'source_ip' => $incident->source_ip,
        ]);

        // Mark notification as sent
        $incident->update(['notification_sent' => true]);
    }

    /**
     * Sanitize request headers for logging.
     */
    private function sanitizeHeaders(?array $headers): ?array
    {
        if (! $headers) {
            return null;
        }

        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key'];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }

    /**
     * Sanitize request payload for logging.
     */
    private function sanitizePayload(?array $payload): ?array
    {
        if (! $payload) {
            return null;
        }

        $sensitiveFields = ['password', 'token', 'api_key', 'secret'];

        foreach ($sensitiveFields as $field) {
            if (isset($payload[$field])) {
                $payload[$field] = '[REDACTED]';
            }
        }

        return $payload;
    }

    /**
     * Calculate average resolution time.
     * @param mixed $incidents
     */
    private function calculateAverageResolutionTime($incidents): ?float
    {
        $resolved = $incidents->whereNotNull('resolved_at');

        if ($resolved->isEmpty()) {
            return null;
        }

        $totalMinutes = $resolved->sum(function ($incident) {
            return $incident->getDurationInMinutes();
        });

        return round($totalMinutes / $resolved->count(), 2);
    }

    /**
     * Get top source IPs.
     * @param mixed $incidents
     */
    private function getTopSourceIps($incidents, int $limit = 10): array
    {
        return $incidents->whereNotNull('source_ip')
            ->groupBy('source_ip')
            ->map->count()
            ->sortDesc()
            ->take($limit)
            ->toArray();
    }
}
