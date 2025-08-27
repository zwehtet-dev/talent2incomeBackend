<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(private AuditService $auditService)
    {
    }

    /**
     * Handle the model "created" event.
     */
    public function created(Model $model): void
    {
        if ($this->shouldAudit($model)) {
            $this->auditService->logCreated($model, [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
            ]);
        }
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated(Model $model): void
    {
        if ($this->shouldAudit($model)) {
            $this->auditService->logUpdated($model, $model->getOriginal(), [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'changed_fields' => array_keys($model->getChanges()),
            ]);
        }
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        if ($this->shouldAudit($model)) {
            $this->auditService->logDeleted($model, [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'soft_deleted' => method_exists($model, 'trashed') && $model->trashed(),
            ]);
        }
    }

    /**
     * Handle the model "restored" event.
     */
    public function restored(Model $model): void
    {
        if ($this->shouldAudit($model)) {
            $this->auditService->log(
                'model.restored',
                $model,
                null,
                $model->getAttributes(),
                class_basename($model) . ' restored',
                'info',
                false,
                [
                    'model_class' => get_class($model),
                    'model_id' => $model->getKey(),
                ]
            );
        }
    }

    /**
     * Determine if the model should be audited.
     */
    private function shouldAudit(Model $model): bool
    {
        // Don't audit the audit log itself to prevent infinite loops
        if ($model instanceof \App\Models\AuditLog) {
            return false;
        }

        // Don't audit session data
        if (get_class($model) === 'Illuminate\Session\DatabaseSessionHandler') {
            return false;
        }

        // Don't audit cache entries
        if (str_contains(get_class($model), 'Cache')) {
            return false;
        }

        // Don't audit temporary or system models
        $excludedModels = [
            'App\Models\PersonalAccessToken', // Laravel Sanctum tokens
        ];

        if (in_array(get_class($model), $excludedModels)) {
            return false;
        }

        return true;
    }
}
