<?php

namespace App\Jobs;

use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\Searchable;

class UpdateSearchIndex implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes
    public int $backoff = 60;

    public function __construct(
        public string $operation, // 'index', 'update', 'delete', 'rebuild'
        public string $model, // 'jobs', 'skills', 'users', 'all'
        public ?array $ids = null, // Specific IDs to process
        public array $options = []
    ) {
        $this->onQueue('search');
    }

    public function handle(): void
    {
        try {
            Log::info('Starting search index update', [
                'operation' => $this->operation,
                'model' => $this->model,
                'ids' => $this->ids ? count($this->ids) : 'all',
                'options' => $this->options,
            ]);

            $startTime = microtime(true);
            $results = [];

            switch ($this->operation) {
                case 'index':
                    $results = $this->indexModels();

                    break;
                case 'update':
                    $results = $this->updateModels();

                    break;
                case 'delete':
                    $results = $this->deleteFromIndex();

                    break;
                case 'rebuild':
                    $results = $this->rebuildIndex();

                    break;
                default:
                    throw new \InvalidArgumentException("Unknown operation: {$this->operation}");
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('Search index update completed', [
                'operation' => $this->operation,
                'model' => $this->model,
                'duration' => $duration,
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Search index update failed', [
                'operation' => $this->operation,
                'model' => $this->model,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Search index update job failed', [
            'operation' => $this->operation,
            'model' => $this->model,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function indexModels(): array
    {
        $results = [];
        $models = $this->getModelsToProcess();

        foreach ($models as $modelClass => $modelName) {
            try {
                $query = $modelClass::query();

                if ($this->ids && $this->model === $modelName) {
                    $query->whereIn('id', $this->ids);
                }

                $batchSize = $this->options['batch_size'] ?? 100;
                $processed = 0;

                $query->chunk($batchSize, function (Collection $records) use (&$processed, $modelName) {
                    $this->indexBatch($records, $modelName);
                    $processed += $records->count();

                    Log::debug('Indexed batch', [
                        'model' => $modelName,
                        'batch_size' => $records->count(),
                        'total_processed' => $processed,
                    ]);
                });

                $results[$modelName] = ['indexed' => $processed];

            } catch (\Exception $e) {
                $results[$modelName] = ['error' => $e->getMessage()];
                Log::warning('Failed to index model', [
                    'model' => $modelName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    protected function updateModels(): array
    {
        $results = [];
        $models = $this->getModelsToProcess();

        foreach ($models as $modelClass => $modelName) {
            try {
                $query = $modelClass::query();

                if ($this->ids && $this->model === $modelName) {
                    $query->whereIn('id', $this->ids);
                } else {
                    // Update recently modified records
                    $since = $this->options['since'] ?? now()->subHours(1);
                    $query->where('updated_at', '>=', $since);
                }

                $records = $query->get();

                if ($records->isNotEmpty()) {
                    $this->indexBatch($records, $modelName);
                }

                $results[$modelName] = ['updated' => $records->count()];

            } catch (\Exception $e) {
                $results[$modelName] = ['error' => $e->getMessage()];
                Log::warning('Failed to update model index', [
                    'model' => $modelName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    protected function deleteFromIndex(): array
    {
        if (! $this->ids) {
            throw new \InvalidArgumentException('IDs are required for delete operation');
        }

        $results = [];
        $models = $this->getModelsToProcess();

        foreach ($models as $modelClass => $modelName) {
            try {
                if ($this->model !== 'all' && $this->model !== $modelName) {
                    continue;
                }

                // Create dummy models with IDs for deletion
                $modelsToDelete = collect($this->ids)->map(function ($id) use ($modelClass) {
                    $model = new $modelClass();
                    $model->id = $id;

                    return $model;
                });

                $this->deleteBatch($modelsToDelete, $modelName);
                $results[$modelName] = ['deleted' => count($this->ids)];

            } catch (\Exception $e) {
                $results[$modelName] = ['error' => $e->getMessage()];
                Log::warning('Failed to delete from index', [
                    'model' => $modelName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    protected function rebuildIndex(): array
    {
        $results = [];
        $models = $this->getModelsToProcess();

        foreach ($models as $modelClass => $modelName) {
            try {
                Log::info('Rebuilding search index', ['model' => $modelName]);

                // Clear existing index
                if (method_exists($modelClass, 'removeAllFromSearch')) {
                    $modelClass::removeAllFromSearch();
                }

                // Re-index all records
                $batchSize = $this->options['batch_size'] ?? 100;
                $processed = 0;

                $modelClass::chunk($batchSize, function (Collection $records) use (&$processed, $modelName) {
                    $this->indexBatch($records, $modelName);
                    $processed += $records->count();

                    Log::debug('Rebuilt batch', [
                        'model' => $modelName,
                        'batch_size' => $records->count(),
                        'total_processed' => $processed,
                    ]);
                });

                $results[$modelName] = ['rebuilt' => $processed];

            } catch (\Exception $e) {
                $results[$modelName] = ['error' => $e->getMessage()];
                Log::warning('Failed to rebuild index', [
                    'model' => $modelName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    protected function indexBatch(Collection $records, string $modelName): void
    {
        try {
            // Filter out records that shouldn't be indexed
            $indexableRecords = $records->filter(function (Model $record) {
                return $this->shouldIndex($record);
            });

            if ($indexableRecords->isEmpty()) {
                return;
            }

            // Use Scout's searchable method for batch indexing
            if (method_exists($indexableRecords->first(), 'searchable')) {
                $indexableRecords->searchable();
            }

            Log::debug('Batch indexed successfully', [
                'model' => $modelName,
                'count' => $indexableRecords->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to index batch', [
                'model' => $modelName,
                'count' => $records->count(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function deleteBatch(Collection $records, string $modelName): void
    {
        try {
            if (method_exists($records->first(), 'unsearchable')) {
                $records->unsearchable();
            }

            Log::debug('Batch deleted from index', [
                'model' => $modelName,
                'count' => $records->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete batch from index', [
                'model' => $modelName,
                'count' => $records->count(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function shouldIndex(Model $record): bool
    {
        // Check if record should be indexed based on its state
        if (method_exists($record, 'shouldBeSearchable')) {
            return $record->shouldBeSearchable();
        }

        // Default indexing rules
        switch (get_class($record)) {
            case Job::class:
                return $record->status === 'open' && $record->is_active ?? true;
            case Skill::class:
                return $record->is_available && $record->is_active ?? true;
            case User::class:
                return $record->is_active && $record->email_verified_at !== null;
            default:
                return true;
        }
    }

    protected function getModelsToProcess(): array
    {
        $allModels = [
            Job::class => 'jobs',
            Skill::class => 'skills',
            User::class => 'users',
        ];

        if ($this->model === 'all') {
            return $allModels;
        }

        $filteredModels = [];
        foreach ($allModels as $class => $name) {
            if ($name === $this->model) {
                $filteredModels[$class] = $name;

                break;
            }
        }

        if (empty($filteredModels)) {
            throw new \InvalidArgumentException("Unknown model: {$this->model}");
        }

        return $filteredModels;
    }
}
