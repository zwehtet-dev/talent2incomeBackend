<?php

namespace App\Traits;

use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;

trait CacheInvalidation
{
    /**
     * Invalidate cache related to this model
     */
    public function invalidateRelatedCache(): void
    {
        $cacheService = app(CacheService::class);

        // Invalidate search cache for all models
        $cacheService->invalidateSearchCache();

        // Model-specific invalidation
        match (class_basename($this)) {
            'User' => $this->invalidateUserRelatedCache($cacheService),
            'Job' => $this->invalidateJobRelatedCache($cacheService),
            'Skill' => $this->invalidateSkillRelatedCache($cacheService),
            'Review' => $this->invalidateReviewRelatedCache($cacheService),
            'Payment' => $this->invalidatePaymentRelatedCache($cacheService),
            'Message' => $this->invalidateMessageRelatedCache($cacheService),
            default => null,
        };
    }

    /**
     * Invalidate model-specific cache
     */
    public function invalidateModelCache(): void
    {
        $cacheService = app(CacheService::class);
        $modelName = strtolower(class_basename($this));

        $cacheService->invalidateByTags([
            $modelName . 's',
            $modelName . ':' . $this->id,
        ]);
    }
    /**
     * Boot the trait
     */
    protected static function bootCacheInvalidation(): void
    {
        static::created(function ($model) {
            $model->invalidateRelatedCache();
        });

        static::updated(function ($model) {
            $model->invalidateRelatedCache();
            $model->invalidateModelCache();
        });

        static::deleted(function ($model) {
            $model->invalidateRelatedCache();
            $model->invalidateModelCache();
        });
    }

    /**
     * Invalidate user-related cache
     */
    private function invalidateUserRelatedCache(CacheService $cacheService): void
    {
        $cacheService->invalidateUserCache($this->id);

        // Invalidate related jobs and skills
        $cacheService->invalidateByTags(['jobs', 'skills']);

        // Invalidate API responses that might include user data
        try {
            Cache::store('redis_api')->clear();
        } catch (\Exception $e) {
            // Fallback to default cache store if Redis is not available
            Cache::clear();
        }
    }

    /**
     * Invalidate job-related cache
     */
    private function invalidateJobRelatedCache(CacheService $cacheService): void
    {
        $cacheService->invalidateJobCache($this->id);

        // Invalidate user cache for job owner
        if ($this->user_id) {
            $cacheService->invalidateUserCache($this->user_id);
        }

        // Invalidate user cache for assigned user
        if ($this->assigned_to) {
            $cacheService->invalidateUserCache($this->assigned_to);
        }

        // Invalidate category-related cache
        if ($this->category_id) {
            $cacheService->invalidateByTags(['category:' . $this->category_id]);
        }
    }

    /**
     * Invalidate skill-related cache
     */
    private function invalidateSkillRelatedCache(CacheService $cacheService): void
    {
        $cacheService->invalidateSkillCache($this->id);

        // Invalidate user cache for skill owner
        if ($this->user_id) {
            $cacheService->invalidateUserCache($this->user_id);
        }

        // Invalidate category-related cache
        if ($this->category_id) {
            $cacheService->invalidateByTags(['category:' . $this->category_id]);
        }
    }

    /**
     * Invalidate review-related cache
     */
    private function invalidateReviewRelatedCache(CacheService $cacheService): void
    {
        // Invalidate cache for both reviewer and reviewee
        if ($this->reviewer_id) {
            $cacheService->invalidateUserCache($this->reviewer_id);
        }

        if ($this->reviewee_id) {
            $cacheService->invalidateUserCache($this->reviewee_id);
        }

        // Invalidate job cache
        if ($this->job_id) {
            $cacheService->invalidateJobCache($this->job_id);
        }

        // Invalidate rating-related cache
        $cacheService->invalidateByTags(['ratings']);
    }

    /**
     * Invalidate payment-related cache
     */
    private function invalidatePaymentRelatedCache(CacheService $cacheService): void
    {
        // Invalidate cache for payer and payee
        if ($this->payer_id) {
            $cacheService->invalidateUserCache($this->payer_id);
        }

        if ($this->payee_id) {
            $cacheService->invalidateUserCache($this->payee_id);
        }

        // Invalidate job cache
        if ($this->job_id) {
            $cacheService->invalidateJobCache($this->job_id);
        }
    }

    /**
     * Invalidate message-related cache
     */
    private function invalidateMessageRelatedCache(CacheService $cacheService): void
    {
        // Invalidate cache for sender and recipient
        if ($this->sender_id) {
            $cacheService->invalidateUserCache($this->sender_id);
        }

        if ($this->recipient_id) {
            $cacheService->invalidateUserCache($this->recipient_id);
        }

        // Invalidate conversation cache
        $cacheService->invalidateByTags(['conversations']);
    }
}
