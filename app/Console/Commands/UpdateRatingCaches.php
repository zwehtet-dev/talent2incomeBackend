<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RatingHistory;
use App\Models\User;
use App\Services\RatingService;
use Illuminate\Console\Command;

class UpdateRatingCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ratings:update-cache 
                            {--user-id= : Update cache for specific user ID}
                            {--force : Force update even if cache is fresh}
                            {--with-history : Also create rating history entries}
                            {--chunk-size=100 : Number of users to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update cached rating statistics for users';

    /**
     * Rating service instance.
     */
    private RatingService $ratingService;

    /**
     * Create a new command instance.
     */
    public function __construct(RatingService $ratingService)
    {
        parent::__construct();
        $this->ratingService = $ratingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user-id');
        $force = $this->option('force');
        $withHistory = $this->option('with-history');
        $chunkSize = (int) $this->option('chunk-size');

        if ($userId) {
            return $this->updateSingleUser((int) $userId, $force, $withHistory);
        }

        return $this->updateAllUsers($force, $withHistory, $chunkSize);
    }

    /**
     * Update rating cache for a single user.
     */
    private function updateSingleUser(int $userId, bool $force, bool $withHistory): int
    {
        $user = User::find($userId);

        if (! $user) {
            $this->error("User with ID {$userId} not found.");

            return 1;
        }

        $this->info("Updating rating cache for user: {$user->full_name} (ID: {$userId})");

        if (! $force && ! $user->isRatingCacheStale()) {
            $this->info('Rating cache is fresh. Use --force to update anyway.');

            return 0;
        }

        $stats = $this->ratingService->calculateUserRatingStats($userId, false);
        $user->updateRatingCache($stats);

        if ($withHistory) {
            RatingHistory::createFromStats($userId, $stats, 'scheduled');
            $this->info('Rating history entry created.');
        }

        $this->info('Rating cache updated successfully.');
        $this->displayUserStats($user, $stats);

        return 0;
    }

    /**
     * Update rating caches for all users.
     */
    private function updateAllUsers(bool $force, bool $withHistory, int $chunkSize): int
    {
        $query = User::query();

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('rating_cache_updated_at')
                    ->orWhere('rating_cache_updated_at', '<', now()->subHour());
            });
        }

        $totalUsers = $query->count();

        if ($totalUsers === 0) {
            $this->info('No users need rating cache updates.');

            return 0;
        }

        $this->info("Updating rating caches for {$totalUsers} users...");

        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();

        $processed = 0;
        $errors = 0;

        $query->chunk($chunkSize, function ($users) use ($withHistory, &$processed, &$errors, $progressBar) {
            foreach ($users as $user) {
                try {
                    $stats = $this->ratingService->calculateUserRatingStats($user->id, false);
                    $user->updateRatingCache($stats);

                    if ($withHistory) {
                        RatingHistory::createFromStats($user->id, $stats, 'scheduled');
                    }

                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    $this->error("Error updating user {$user->id}: " . $e->getMessage());
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        $this->info('Rating cache update completed.');
        $this->info("Processed: {$processed} users");

        if ($errors > 0) {
            $this->warn("Errors: {$errors} users");
        }

        return $errors > 0 ? 1 : 0;
    }

    /**
     * Display user rating statistics.
     */
    private function displayUserStats(User $user, array $stats): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Reviews', $stats['total_reviews']],
                ['Simple Average', $stats['simple_average']],
                ['Weighted Average', $stats['weighted_average']],
                ['Time Weighted', $stats['time_weighted_average']],
                ['Decayed Rating', $stats['decayed_rating']],
                ['Quality Score', $stats['quality_score']],
                ['Trend Direction', $stats['trend']['direction']],
                ['Rating Eligible', $user->is_rating_eligible ? 'Yes' : 'No'],
            ]
        );
    }
}
