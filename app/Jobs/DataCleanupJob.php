<?php

namespace App\Jobs;

use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataCleanupJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800; // 30 minutes
    public int $backoff = 300; // 5 minutes

    public function __construct(
        public string $cleanupType,
        public array $options = []
    ) {
        $this->onQueue('cleanup');
    }

    public function handle(): void
    {
        try {
            Log::info('Starting data cleanup', [
                'type' => $this->cleanupType,
                'options' => $this->options,
            ]);

            $startTime = microtime(true);
            $results = [];

            switch ($this->cleanupType) {
                case 'expired_jobs':
                    $results = $this->cleanupExpiredJobs();

                    break;
                case 'old_messages':
                    $results = $this->archiveOldMessages();

                    break;
                case 'inactive_users':
                    $results = $this->cleanupInactiveUsers();

                    break;
                case 'completed_payments':
                    $results = $this->archiveCompletedPayments();

                    break;
                case 'old_reviews':
                    $results = $this->archiveOldReviews();

                    break;
                case 'temp_files':
                    $results = $this->cleanupTempFiles();

                    break;
                case 'logs':
                    $results = $this->cleanupOldLogs();

                    break;
                case 'full_cleanup':
                    $results = $this->performFullCleanup();

                    break;
                default:
                    throw new \InvalidArgumentException("Unknown cleanup type: {$this->cleanupType}");
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Data cleanup completed', [
                'type' => $this->cleanupType,
                'duration' => $duration,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Data cleanup failed', [
                'type' => $this->cleanupType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Data cleanup job failed', [
            'type' => $this->cleanupType,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function cleanupExpiredJobs(): array
    {
        $cutoffDate = Carbon::now()->subDays($this->options['days_old'] ?? 90);

        // Archive expired jobs older than cutoff date
        $expiredJobs = Job::where('status', 'expired')
            ->where('updated_at', '<', $cutoffDate)
            ->get();

        $archivedCount = 0;
        $deletedCount = 0;

        foreach ($expiredJobs as $job) {
            // Create archive record
            $this->archiveRecord('jobs', $job->toArray());

            // Soft delete the job
            $job->delete();
            $archivedCount++;
        }

        // Permanently delete very old expired jobs
        $veryOldCutoff = Carbon::now()->subDays($this->options['permanent_delete_days'] ?? 365);
        $deletedCount = Job::onlyTrashed()
            ->where('status', 'expired')
            ->where('deleted_at', '<', $veryOldCutoff)
            ->forceDelete();

        return [
            'archived' => $archivedCount,
            'permanently_deleted' => $deletedCount,
        ];
    }

    protected function archiveOldMessages(): array
    {
        $cutoffDate = Carbon::now()->subDays($this->options['days_old'] ?? 180);

        $oldMessages = Message::where('created_at', '<', $cutoffDate)
            ->whereHas('sender', function ($query) {
                $query->where('is_active', false);
            })
            ->orWhereHas('recipient', function ($query) {
                $query->where('is_active', false);
            })
            ->get();

        $archivedCount = 0;

        foreach ($oldMessages as $message) {
            // Archive message with anonymized data
            $this->archiveRecord('messages', [
                'id' => $message->id,
                'sender_id' => null, // Anonymize
                'recipient_id' => null, // Anonymize
                'job_id' => $message->job_id,
                'content_length' => strlen($message->content),
                'is_read' => $message->is_read,
                'created_at' => $message->created_at,
                'archived_at' => now(),
            ]);

            $message->delete();
            $archivedCount++;
        }

        return ['archived' => $archivedCount];
    }

    protected function cleanupInactiveUsers(): array
    {
        $cutoffDate = Carbon::now()->subDays($this->options['days_inactive'] ?? 730); // 2 years

        $inactiveUsers = User::where('is_active', false)
            ->where('updated_at', '<', $cutoffDate)
            ->whereDoesntHave('jobs', function ($query) {
                $query->whereIn('status', ['in_progress', 'completed']);
            })
            ->whereDoesntHave('payments', function ($query) {
                $query->whereIn('status', ['held', 'pending']);
            })
            ->get();

        $anonymizedCount = 0;

        foreach ($inactiveUsers as $user) {
            // Archive user data before anonymization
            $this->archiveRecord('users', $user->toArray());

            // Anonymize user data
            $user->update([
                'email' => 'deleted_' . $user->id . '@example.com',
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'bio' => null,
                'phone' => null,
                'avatar' => null,
                'location' => null,
                'is_active' => false,
            ]);

            $anonymizedCount++;
        }

        return ['anonymized' => $anonymizedCount];
    }

    protected function archiveCompletedPayments(): array
    {
        $cutoffDate = Carbon::now()->subDays($this->options['days_old'] ?? 365);

        $oldPayments = Payment::whereIn('status', ['released', 'refunded'])
            ->where('updated_at', '<', $cutoffDate)
            ->get();

        $archivedCount = 0;

        foreach ($oldPayments as $payment) {
            // Archive payment with essential data only
            $this->archiveRecord('payments', [
                'id' => $payment->id,
                'job_id' => $payment->job_id,
                'amount' => $payment->amount,
                'platform_fee' => $payment->platform_fee,
                'status' => $payment->status,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
                'archived_at' => now(),
            ]);

            // Remove sensitive payment method data
            $payment->update([
                'payment_method' => 'archived',
                'transaction_id' => null,
            ]);

            $archivedCount++;
        }

        return ['archived' => $archivedCount];
    }

    protected function archiveOldReviews(): array
    {
        $cutoffDate = Carbon::now()->subDays($this->options['days_old'] ?? 1095); // 3 years

        $oldReviews = Review::where('created_at', '<', $cutoffDate)
            ->whereHas('reviewee', function ($query) {
                $query->where('is_active', false);
            })
            ->get();

        $archivedCount = 0;

        foreach ($oldReviews as $review) {
            $this->archiveRecord('reviews', $review->toArray());
            $review->delete();
            $archivedCount++;
        }

        return ['archived' => $archivedCount];
    }

    protected function cleanupTempFiles(): array
    {
        $tempDirs = ['temp', 'uploads/temp', 'reports/temp'];
        $cutoffTime = Carbon::now()->subHours($this->options['hours_old'] ?? 24);

        $deletedCount = 0;
        $deletedSize = 0;

        foreach ($tempDirs as $dir) {
            if (! Storage::exists($dir)) {
                continue;
            }

            $files = Storage::files($dir);

            foreach ($files as $file) {
                $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));

                if ($lastModified->lt($cutoffTime)) {
                    $size = Storage::size($file);
                    Storage::delete($file);
                    $deletedCount++;
                    $deletedSize += $size;
                }
            }
        }

        return [
            'files_deleted' => $deletedCount,
            'size_freed' => $this->formatBytes($deletedSize),
        ];
    }

    protected function cleanupOldLogs(): array
    {
        $logPath = storage_path('logs');
        $cutoffDate = Carbon::now()->subDays($this->options['days_old'] ?? 30);

        $deletedCount = 0;
        $deletedSize = 0;

        if (is_dir($logPath)) {
            $files = glob($logPath . '/*.log');

            foreach ($files as $file) {
                $lastModified = Carbon::createFromTimestamp(filemtime($file));

                if ($lastModified->lt($cutoffDate)) {
                    $size = filesize($file);
                    unlink($file);
                    $deletedCount++;
                    $deletedSize += $size;
                }
            }
        }

        return [
            'files_deleted' => $deletedCount,
            'size_freed' => $this->formatBytes($deletedSize),
        ];
    }

    protected function performFullCleanup(): array
    {
        $results = [];

        $cleanupTasks = [
            'expired_jobs' => ['days_old' => 90],
            'old_messages' => ['days_old' => 180],
            'inactive_users' => ['days_inactive' => 730],
            'completed_payments' => ['days_old' => 365],
            'old_reviews' => ['days_old' => 1095],
            'temp_files' => ['hours_old' => 24],
            'logs' => ['days_old' => 30],
        ];

        foreach ($cleanupTasks as $task => $options) {
            try {
                $this->cleanupType = $task;
                $this->options = array_merge($this->options, $options);

                $results[$task] = $this->handle();
            } catch (\Exception $e) {
                $results[$task] = ['error' => $e->getMessage()];
                Log::warning('Cleanup task failed', [
                    'task' => $task,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    protected function archiveRecord(string $table, array $data): void
    {
        $archiveTable = "archived_{$table}";

        // Create archive table if it doesn't exist
        if (! DB::getSchemaBuilder()->hasTable($archiveTable)) {
            $this->createArchiveTable($table, $archiveTable);
        }

        // Insert archived data
        DB::table($archiveTable)->insert(array_merge($data, [
            'archived_at' => now(),
            'archived_by' => 'system_cleanup',
        ]));
    }

    protected function createArchiveTable(string $sourceTable, string $archiveTable): void
    {
        DB::statement("CREATE TABLE {$archiveTable} LIKE {$sourceTable}");
        DB::statement("ALTER TABLE {$archiveTable} ADD COLUMN archived_at TIMESTAMP NULL");
        DB::statement("ALTER TABLE {$archiveTable} ADD COLUMN archived_by VARCHAR(255) NULL");
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
