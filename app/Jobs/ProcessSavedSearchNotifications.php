<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SavedSearch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessSavedSearchNotifications implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing saved search notifications');

        $savedSearches = SavedSearch::needingNotificationCheck()
            ->with('user')
            ->get();

        Log::info("Found {$savedSearches->count()} saved searches needing notification check");

        foreach ($savedSearches as $savedSearch) {
            try {
                $this->processSavedSearchNotification($savedSearch);
            } catch (\Exception $e) {
                Log::error('Failed to process saved search notification', [
                    'saved_search_id' => $savedSearch->id,
                    'user_id' => $savedSearch->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed processing saved search notifications');
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessSavedSearchNotifications job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Process notification for a single saved search.
     */
    private function processSavedSearchNotification(SavedSearch $savedSearch): void
    {
        $newResults = $savedSearch->getNewResults();

        if ($newResults->isEmpty()) {
            Log::debug("No new results for saved search {$savedSearch->id}");

            return;
        }

        Log::info("Found {$newResults->count()} new results for saved search {$savedSearch->id}");

        // Send notification email
        try {
            Mail::to($savedSearch->user->email)->send(
                new \App\Mail\SavedSearchNotification($savedSearch, $newResults)
            );

            // Mark notification as sent
            $savedSearch->markNotificationSent();

            Log::info("Sent notification for saved search {$savedSearch->id} to {$savedSearch->user->email}");
        } catch (\Exception $e) {
            Log::error('Failed to send saved search notification email', [
                'saved_search_id' => $savedSearch->id,
                'user_email' => $savedSearch->user->email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
