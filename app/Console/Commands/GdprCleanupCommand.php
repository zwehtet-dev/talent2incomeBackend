<?php

namespace App\Console\Commands;

use App\Models\GdprRequest;
use App\Services\AuditService;
use App\Services\GdprService;
use Illuminate\Console\Command;

class GdprCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gdpr:cleanup 
                           {--expired-exports : Clean up expired export files}
                           {--old-requests : Clean up old completed requests}
                           {--all : Clean up all eligible GDPR data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired GDPR export files and old requests';

    /**
     * Execute the console command.
     */
    public function handle(GdprService $gdprService, AuditService $auditService): int
    {
        $this->info('Starting GDPR cleanup...');

        $cleanupExpiredExports = $this->option('expired-exports') || $this->option('all');
        $cleanupOldRequests = $this->option('old-requests') || $this->option('all');

        $results = [];

        if ($cleanupExpiredExports) {
            $this->info('Cleaning up expired export files...');
            $expiredCount = $gdprService->cleanupExpiredExports();
            $results['expired_exports'] = $expiredCount;
            $this->info("Cleaned up {$expiredCount} expired export files");
        }

        if ($cleanupOldRequests) {
            $this->info('Cleaning up old completed requests...');
            $oldRequestsCount = $this->cleanupOldRequests();
            $results['old_requests'] = $oldRequestsCount;
            $this->info("Cleaned up {$oldRequestsCount} old completed requests");
        }

        if (empty($results)) {
            $this->warn('No cleanup options specified. Use --expired-exports, --old-requests, or --all');

            return 1;
        }

        // Log the cleanup
        $auditService->log(
            'gdpr.cleanup_executed',
            null,
            null,
            null,
            'GDPR cleanup executed',
            'info',
            true,
            [
                'results' => $results,
                'total_cleaned' => array_sum($results),
                'executed_via' => 'console_command',
            ]
        );

        $totalCleaned = array_sum($results);
        $this->info("GDPR cleanup completed. Total items cleaned: {$totalCleaned}");

        return 0;
    }

    /**
     * Clean up old completed GDPR requests.
     */
    private function cleanupOldRequests(): int
    {
        // Clean up completed requests older than 1 year
        $cutoffDate = now()->subYear();

        $count = GdprRequest::where('status', GdprRequest::STATUS_COMPLETED)
            ->where('completed_at', '<', $cutoffDate)
            ->delete();

        // Also clean up rejected requests older than 6 months
        $rejectedCutoff = now()->subMonths(6);
        $rejectedCount = GdprRequest::where('status', GdprRequest::STATUS_REJECTED)
            ->where('completed_at', '<', $rejectedCutoff)
            ->delete();

        return $count + $rejectedCount;
    }
}
