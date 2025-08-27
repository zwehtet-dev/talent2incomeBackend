<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use App\Services\ComplianceService;
use Illuminate\Console\Command;

class ComplianceCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:cleanup 
                           {--dry-run : Show what would be cleaned up without actually doing it}
                           {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run data retention cleanup based on compliance policies';

    /**
     * Execute the console command.
     */
    public function handle(ComplianceService $complianceService, AuditService $auditService): int
    {
        $this->info('Starting compliance cleanup...');

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no data will be deleted');

            // Get cleanup candidates
            $candidates = $complianceService->getCleanupCandidates();

            if (empty($candidates) || array_sum($candidates) === 0) {
                $this->info('No data found that exceeds retention periods');

                return 0;
            }

            $this->table(
                ['Data Type', 'Records to Clean'],
                collect($candidates)->map(fn ($count, $type) => [$type, number_format($count)])->toArray()
            );

            $this->info('Total records that would be cleaned: ' . number_format(array_sum($candidates)));

            return 0;
        }

        // Show what will be cleaned up
        $candidates = $complianceService->getCleanupCandidates();

        if (empty($candidates) || array_sum($candidates) === 0) {
            $this->info('No data found that exceeds retention periods');

            return 0;
        }

        $this->table(
            ['Data Type', 'Records to Clean'],
            collect($candidates)->map(fn ($count, $type) => [$type, number_format($count)])->toArray()
        );

        $totalRecords = array_sum($candidates);
        $this->warn("This will permanently delete {$totalRecords} records");

        if (! $force && ! $this->confirm('Do you want to continue?')) {
            $this->info('Cleanup cancelled');

            return 0;
        }

        // Perform cleanup
        $this->info('Running data retention cleanup...');

        $results = $complianceService->runDataRetentionCleanup();

        // Display results
        $this->table(
            ['Data Type', 'Records Cleaned'],
            collect($results)->map(fn ($count, $type) => [$type, number_format($count)])->toArray()
        );

        $totalCleaned = array_sum($results);
        $this->info("Cleanup completed. Total records cleaned: {$totalCleaned}");

        // Log the cleanup
        $auditService->log(
            'compliance.cleanup_executed',
            null,
            null,
            null,
            'Automated compliance cleanup executed',
            'info',
            false,
            [
                'results' => $results,
                'total_cleaned' => $totalCleaned,
                'executed_via' => 'console_command',
            ]
        );

        return 0;
    }
}
