<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Models\Skill;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManageSearchIndexes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:manage 
                            {action : The action to perform (import, flush, stats, cleanup)}
                            {--model= : Specific model to target (Job, Skill)}
                            {--force : Force the action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage search indexes for jobs and skills';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $model = $this->option('model');

        return match ($action) {
            'import' => $this->importIndexes($model),
            'flush' => $this->flushIndexes($model),
            'stats' => $this->showStats(),
            'cleanup' => $this->cleanupAnalytics(),
            default => (function () use ($action) {
                $this->error("Unknown action: {$action}");

                return 1;
            })(),
        };
    }

    /**
     * Import models into search indexes.
     */
    private function importIndexes(?string $model): int
    {
        $models = $model ? [$model] : ['Job', 'Skill'];

        foreach ($models as $modelName) {
            $modelClass = "App\\Models\\{$modelName}";

            if (! class_exists($modelClass)) {
                $this->error("Model {$modelName} does not exist");

                continue;
            }

            $this->info("Importing {$modelName} models to search index...");

            $count = $modelClass::count();
            $this->info("Found {$count} {$modelName} records");

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            $modelClass::chunk(100, function ($records) use ($bar) {
                foreach ($records as $record) {
                    if ($record->shouldBeSearchable()) {
                        $record->searchable();
                    }
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info("{$modelName} import completed");
        }

        return 0;
    }

    /**
     * Flush search indexes.
     */
    private function flushIndexes(?string $model): int
    {
        if (! $this->option('force') && ! $this->confirm('This will remove all search indexes. Are you sure?')) {
            return 1;
        }

        $models = $model ? [$model] : ['Job', 'Skill'];

        foreach ($models as $modelName) {
            $modelClass = "App\\Models\\{$modelName}";

            if (! class_exists($modelClass)) {
                $this->error("Model {$modelName} does not exist");

                continue;
            }

            $this->info("Flushing {$modelName} search index...");
            $modelClass::removeAllFromSearch();
            $this->info("{$modelName} index flushed");
        }

        return 0;
    }

    /**
     * Show search statistics.
     */
    private function showStats(): int
    {
        $this->info('Search Index Statistics');
        $this->line('========================');

        // Job statistics
        $totalJobs = Job::count();
        $searchableJobs = Job::whereIn('status', [Job::STATUS_OPEN])->count();

        $this->line('Jobs:');
        $this->line("  Total: {$totalJobs}");
        $this->line("  Searchable: {$searchableJobs}");

        // Skill statistics
        $totalSkills = Skill::count();
        $searchableSkills = Skill::where('is_active', true)
            ->where('is_available', true)
            ->count();

        $this->line('Skills:');
        $this->line("  Total: {$totalSkills}");
        $this->line("  Searchable: {$searchableSkills}");

        // Search analytics
        $this->newLine();
        $this->info('Search Analytics (Last 30 days)');
        $this->line('=================================');

        $totalSearches = DB::table('search_analytics')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $searchesByType = DB::table('search_analytics')
            ->select('type', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $topQueries = DB::table('search_analytics')
            ->select('query', DB::raw('COUNT(*) as count'))
            ->where('query', '!=', '')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'query')
            ->toArray();

        $this->line("Total searches: {$totalSearches}");

        if (! empty($searchesByType)) {
            $this->line('Searches by type:');
            foreach ($searchesByType as $type => $count) {
                $this->line("  {$type}: {$count}");
            }
        }

        if (! empty($topQueries)) {
            $this->line('Top queries:');
            foreach ($topQueries as $query => $count) {
                $this->line("  \"{$query}\": {$count}");
            }
        }

        return 0;
    }

    /**
     * Cleanup old search analytics.
     */
    private function cleanupAnalytics(): int
    {
        $days = $this->ask('Delete analytics older than how many days?', '90');

        if (! is_numeric($days) || $days < 1) {
            $this->error('Invalid number of days');

            return 1;
        }

        $cutoffDate = now()->subDays((int) $days);

        $count = DB::table('search_analytics')
            ->where('created_at', '<', $cutoffDate)
            ->count();

        if ($count === 0) {
            $this->info('No old analytics to clean up');

            return 0;
        }

        if (! $this->option('force') && ! $this->confirm("This will delete {$count} analytics records older than {$days} days. Continue?")) {
            return 1;
        }

        $deleted = DB::table('search_analytics')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Deleted {$deleted} old analytics records");

        return 0;
    }
}
