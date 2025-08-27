<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RatingHistory;
use App\Models\User;
use App\Services\RatingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateUserRatingCache implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The user ID to update.
     */
    public int $userId;

    /**
     * Whether to create a rating history entry.
     */
    public bool $createHistory;

    /**
     * The trigger for this update.
     */
    public string $trigger;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId, bool $createHistory = false, string $trigger = 'background')
    {
        $this->userId = $userId;
        $this->createHistory = $createHistory;
        $this->trigger = $trigger;
    }

    /**
     * Execute the job.
     */
    public function handle(RatingService $ratingService): void
    {
        try {
            $user = User::find($this->userId);

            if (! $user) {
                Log::warning('User not found for rating cache update', ['user_id' => $this->userId]);

                return;
            }

            // Calculate fresh rating statistics
            $stats = $ratingService->calculateUserRatingStats($this->userId, false);

            // Update user's cached rating
            $user->updateRatingCache($stats);

            // Create rating history entry if requested
            if ($this->createHistory) {
                RatingHistory::createFromStats($this->userId, $stats, $this->trigger);
            }

            Log::info('Rating cache updated successfully', [
                'user_id' => $this->userId,
                'weighted_average' => $stats['weighted_average'],
                'quality_score' => $stats['quality_score'],
                'trigger' => $this->trigger,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update rating cache', [
                'user_id' => $this->userId,
                'trigger' => $this->trigger,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Rating cache update job failed', [
            'user_id' => $this->userId,
            'trigger' => $this->trigger,
            'error' => $exception->getMessage(),
        ]);
    }
}
