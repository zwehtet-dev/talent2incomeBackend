<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessSavedSearchNotifications as ProcessSavedSearchNotificationsJob;
use Illuminate\Console\Command;

class ProcessSavedSearchNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saved-searches:process-notifications
                            {--force : Force processing even if no searches need notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process saved search notifications for users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Processing saved search notifications...');

        try {
            // Dispatch the job to process notifications
            ProcessSavedSearchNotificationsJob::dispatch();

            $this->info('Saved search notification processing job dispatched successfully.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to dispatch saved search notification job: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
