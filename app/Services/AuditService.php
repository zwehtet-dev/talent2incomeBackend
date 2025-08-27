<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Log an audit event.
     */
    public function log(
        string $eventType,
        Model $auditable = null,
        array $oldValues = null,
        array $newValues = null,
        string $description = null,
        string $severity = 'info',
        bool $isSensitive = false,
        array $metadata = []
    ): AuditLog {
        $request = request();
        $user = Auth::user();

        return AuditLog::create([
            'event_type' => $eventType,
            'auditable_type' => $auditable ? get_class($auditable) : 'system',
            'auditable_id' => $auditable?->getKey(),
            'user_id' => $user?->id,
            'user_type' => $user ? get_class($user) : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
            'http_method' => $request?->method(),
            'request_data' => $this->sanitizeRequestData($request),
            'session_id' => $request && $request->hasSession() ? $request->session()->getId() : null,
            'transaction_id' => $this->generateTransactionId(),
            'description' => $description,
            'metadata' => $metadata,
            'severity' => $severity,
            'is_sensitive' => $isSensitive,
        ]);
    }

    /**
     * Log user authentication events.
     */
    public function logAuth(string $event, Model $user = null, bool $success = true): AuditLog
    {
        return $this->log(
            eventType: "auth.{$event}",
            auditable: $user,
            description: ucfirst($event) . ($success ? ' successful' : ' failed'),
            severity: $success ? 'info' : 'warning',
            isSensitive: true
        );
    }

    /**
     * Log model creation.
     */
    public function logCreated(Model $model, array $metadata = []): AuditLog
    {
        return $this->log(
            eventType: 'model.created',
            auditable: $model,
            newValues: $model->getAttributes(),
            description: class_basename($model) . ' created',
            metadata: $metadata
        );
    }

    /**
     * Log model updates.
     */
    public function logUpdated(Model $model, array $oldValues, array $metadata = []): AuditLog
    {
        return $this->log(
            eventType: 'model.updated',
            auditable: $model,
            oldValues: $oldValues,
            newValues: $model->getChanges(),
            description: class_basename($model) . ' updated',
            metadata: $metadata
        );
    }

    /**
     * Log model deletion.
     */
    public function logDeleted(Model $model, array $metadata = []): AuditLog
    {
        return $this->log(
            eventType: 'model.deleted',
            auditable: $model,
            oldValues: $model->getOriginal(),
            description: class_basename($model) . ' deleted',
            metadata: $metadata
        );
    }

    /**
     * Log security events.
     */
    public function logSecurity(
        string $event,
        string $description,
        string $severity = 'warning',
        array $metadata = []
    ): AuditLog {
        return $this->log(
            eventType: "security.{$event}",
            description: $description,
            severity: $severity,
            isSensitive: true,
            metadata: $metadata
        );
    }

    /**
     * Log payment events.
     */
    public function logPayment(
        string $event,
        Model $payment,
        array $metadata = []
    ): AuditLog {
        return $this->log(
            eventType: "payment.{$event}",
            auditable: $payment,
            description: "Payment {$event}",
            severity: 'info',
            isSensitive: true,
            metadata: $metadata
        );
    }

    /**
     * Log admin actions.
     */
    public function logAdmin(
        string $action,
        Model $target = null,
        array $metadata = []
    ): AuditLog {
        return $this->log(
            eventType: "admin.{$action}",
            auditable: $target,
            description: "Admin action: {$action}",
            severity: 'info',
            metadata: $metadata
        );
    }

    /**
     * Get audit logs with filtering.
     */
    public function getLogs(array $filters = [], int $perPage = 50)
    {
        $query = AuditLog::with(['user', 'auditable'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['event_type'])) {
            $query->eventType($filters['event_type']);
        }

        if (isset($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        if (isset($filters['severity'])) {
            $query->severity($filters['severity']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        if (isset($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (isset($filters['sensitive'])) {
            $query->sensitive($filters['sensitive']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Verify audit log integrity.
     */
    public function verifyIntegrity(AuditLog $log): bool
    {
        return $log->verifyIntegrity();
    }

    /**
     * Clean up old audit logs based on retention policy.
     */
    public function cleanup(int $retentionDays = 365): int
    {
        $cutoffDate = now()->subDays($retentionDays);

        return AuditLog::where('created_at', '<', $cutoffDate)
            ->where('is_sensitive', false)
            ->delete();
    }

    /**
     * Archive old sensitive logs instead of deleting them.
     */
    public function archiveSensitiveLogs(int $retentionDays = 2555): int // 7 years
    {
        $cutoffDate = now()->subDays($retentionDays);

        // In a real implementation, you would move these to an archive storage
        // For now, we'll just mark them as archived
        return AuditLog::where('created_at', '<', $cutoffDate)
            ->where('is_sensitive', true)
            ->update(['metadata->archived' => true]);
    }

    /**
     * Generate transaction ID for grouping related actions.
     */
    private function generateTransactionId(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Sanitize request data for logging.
     */
    private function sanitizeRequestData(Request $request = null): ?array
    {
        if (! $request) {
            return null;
        }

        $data = $request->all();

        // Remove sensitive fields
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_key',
            'secret',
            'credit_card',
            'cvv',
            'ssn',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}
