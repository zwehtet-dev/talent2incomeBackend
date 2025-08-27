<?php

namespace App\Console\Commands;

use App\Services\CacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ManageCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:manage 
                            {action : The action to perform (clear, warm, stats, flush)}
                            {--store= : Specific cache store to target}
                            {--tags= : Comma-separated list of tags to target}';

    /**
     * The console command description.
     */
    protected $description = 'Manage application cache with advanced operations';

    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $store = $this->option('store');
        $tags = $this->option('tags') ? explode(',', $this->option('tags')) : [];

        return match ($action) {
            'clear' => $this->clearCache($store, $tags),
            'warm' => $this->warmCache(),
            'stats' => $this->showStats(),
            'flush' => $this->flushCache($store),
            default => (function () use ($action) {
                $this->error("Unknown action: {$action}");

                return 1;
            })(),
        };
    }

    /**
     * Clear cache by store or tags
     * @param array<string> $tags
     */
    private function clearCache(?string $store, array $tags): int
    {
        if (! empty($tags)) {
            $this->info('Clearing cache for tags: ' . implode(', ', $tags));
            $this->cacheService->invalidateByTags($tags);
            $this->info('Cache cleared successfully for specified tags.');

            return 0;
        }

        if ($store) {
            $this->info("Clearing cache store: {$store}");
            Cache::store($store)->clear();
            $this->info("Cache store '{$store}' cleared successfully.");

            return 0;
        }

        $this->info('Clearing all cache stores...');

        $stores = ['redis', 'redis_api', 'redis_search', 'redis_sessions'];
        foreach ($stores as $storeName) {
            try {
                Cache::store($storeName)->clear();
                $this->line("✓ Cleared {$storeName}");
            } catch (\Exception $e) {
                $this->warn("✗ Failed to clear {$storeName}: " . $e->getMessage());
            }
        }

        $this->info('All cache stores cleared successfully.');

        return 0;
    }

    /**
     * Warm up cache with frequently accessed data
     */
    private function warmCache(): int
    {
        $this->info('Warming up cache...');

        $this->withProgressBar(['categories', 'featured_jobs', 'top_skills'], function ($item) {
            match ($item) {
                'categories' => $this->warmCategories(),
                'featured_jobs' => $this->warmFeaturedJobs(),
                'top_skills' => $this->warmTopSkills(),
            };
        });

        $this->newLine(2);
        $this->info('Cache warmed up successfully.');

        return 0;
    }

    /**
     * Show cache statistics
     */
    private function showStats(): int
    {
        $this->info('Cache Statistics:');
        $this->newLine();

        $stats = $this->cacheService->getCacheStats();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Memory Usage', $stats['memory_usage']],
                ['Total Keys', number_format($stats['total_keys'])],
                ['Hit Rate', $stats['hit_rate'] . '%'],
                ['Connections', $stats['connections']],
            ]
        );

        // Show store-specific stats
        $stores = ['redis', 'redis_api', 'redis_search', 'redis_sessions'];

        $this->newLine();
        $this->info('Store-specific key counts:');

        foreach ($stores as $store) {
            try {
                $connection = \Illuminate\Support\Facades\Redis::connection($store === 'redis' ? 'cache' : str_replace('redis_', '', $store));
                $keyCount = $connection->dbsize();
                $this->line("  {$store}: " . number_format($keyCount) . ' keys');
            } catch (\Exception $e) {
                $this->line("  {$store}: Error - " . $e->getMessage());
            }
        }

        return 0;
    }

    /**
     * Flush specific cache store
     */
    private function flushCache(?string $store): int
    {
        if (! $store) {
            $this->error('Store name is required for flush action.');

            return 1;
        }

        $this->warn("This will completely flush the '{$store}' cache store.");

        if (! $this->confirm('Are you sure you want to continue?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        try {
            Cache::store($store)->clear();
            $this->info("Cache store '{$store}' flushed successfully.");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to flush cache store '{$store}': " . $e->getMessage());

            return 1;
        }
    }

    /**
     * Warm categories cache
     */
    private function warmCategories(): void
    {
        $this->cacheService->cacheQuery('categories:active', function () {
            return \App\Models\Category::where('is_active', true)->get();
        }, CacheService::VERY_LONG_TTL, ['categories']);
    }

    /**
     * Warm featured jobs cache
     */
    private function warmFeaturedJobs(): void
    {
        $this->cacheService->cacheQuery('jobs:featured', function () {
            return \App\Models\Job::where('status', 'open')
                ->where('is_urgent', true)
                ->with(['user', 'category'])
                ->limit(10)
                ->get();
        }, CacheService::MEDIUM_TTL, ['jobs']);
    }

    /**
     * Warm top skills cache
     */
    private function warmTopSkills(): void
    {
        $this->cacheService->cacheQuery('skills:top_rated', function () {
            return \App\Models\Skill::where('is_available', true)
                ->whereHas('user', function ($query) {
                    $query->where('average_rating', '>=', 4.5);
                })
                ->with(['user', 'category'])
                ->limit(20)
                ->get();
        }, CacheService::MEDIUM_TTL, ['skills']);
    }
}
