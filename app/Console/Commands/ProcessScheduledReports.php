<?php

namespace App\Console\Commands;

use App\Services\ReportGenerationService;
use Illuminate\Console\Command;

class ProcessScheduledReports extends Command
{
    protected $signature = 'reports:process {--force : Force processing of all active reports}';
    protected $description = 'Process scheduled reports and send them to recipients';

    protected ReportGenerationService $reportService;

    public function __construct(ReportGenerationService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    public function handle(): int
    {
        $this->info('Processing scheduled reports...');

        try {
            $results = $this->reportService->processScheduledReports();

            if (empty($results)) {
                $this->info('No scheduled reports due for processing.');

                return self::SUCCESS;
            }

            $successCount = 0;
            $errorCount = 0;

            foreach ($results as $result) {
                if ($result['status'] === 'success') {
                    $successCount++;
                    $this->info("✓ Processed scheduled report ID: {$result['scheduled_report_id']}");
                } else {
                    $errorCount++;
                    $this->error("✗ Failed to process scheduled report ID: {$result['scheduled_report_id']}");
                    $this->error("  Error: {$result['error']}");
                }
            }

            $this->newLine();
            $this->info('Report processing completed!');
            $this->info("Successfully processed: {$successCount}");

            if ($errorCount > 0) {
                $this->error("Failed to process: {$errorCount}");

                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error processing scheduled reports: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
