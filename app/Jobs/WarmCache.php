<?php

namespace App\Jobs;

use App\Services\CacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarmCache implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected array $cacheTypes;

    /**
     * Create a new job instance.
     */
    public function __construct(array $cacheTypes = ['all'])
    {
        $this->cacheTypes = $cacheTypes;
    }

    /**
     * Execute the job.
     */
    public function handle(CacheService $cacheService): void
    {
        Log::info('Starting cache warming process', ['types' => $this->cacheTypes]);

        try {
            if (in_array('all', $this->cacheTypes) || in_array('categories', $this->cacheTypes)) {
                $this->warmCategories($cacheService);
            }

            if (in_array('all', $this->cacheTypes) || in_array('featured_jobs', $this->cacheTypes)) {
                $this->warmFeaturedJobs($cacheService);
            }

            if (in_array('all', $this->cacheTypes) || in_array('top_skills', $this->cacheTypes)) {
                $this->warmTopSkills($cacheService);
            }

            if (in_array('all', $this->cacheTypes) || in_array('popular_searches', $this->cacheTypes)) {
                $this->warmPopularSearches($cacheService);
            }

            if (in_array('all', $this->cacheTypes) || in_array('user_stats', $this->cacheTypes)) {
                $this->warmUserStats($cacheService);
            }

            Log::info('Cache warming completed successfully');
        } catch (\Exception $e) {
            Log::error('Cache warming failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Warm categories cache
     */
    private function warmCategories(CacheService $cacheService): void
    {
        $cacheService->cacheQuery('categories:active', function () {
            return \App\Models\Category::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description', 'icon']);
        }, CacheService::VERY_LONG_TTL, ['categories']);

        $cacheService->cacheQuery('categories:with_counts', function () {
            return \App\Models\Category::where('is_active', true)
                ->withCount(['jobs' => function ($query) {
                    $query->where('status', 'open');
                }])
                ->withCount(['skills' => function ($query) {
                    $query->where('is_available', true);
                }])
                ->orderBy('jobs_count', 'desc')
                ->get();
        }, CacheService::LONG_TTL, ['categories']);
    }

    /**
     * Warm featured jobs cache
     */
    private function warmFeaturedJobs(CacheService $cacheService): void
    {
        $cacheService->cacheQuery('jobs:featured', function () {
            return \App\Models\Job::where('status', 'open')
                ->where('is_urgent', true)
                ->with(['user:id,first_name,last_name,avatar,average_rating', 'category:id,name,slug'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
        }, CacheService::MEDIUM_TTL, ['jobs']);

        $cacheService->cacheQuery('jobs:recent', function () {
            return \App\Models\Job::where('status', 'open')
                ->with(['user:id,first_name,last_name,avatar,average_rating', 'category:id,name,slug'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }, CacheService::SHORT_TTL, ['jobs']);

        $cacheService->cacheQuery('jobs:high_budget', function () {
            return \App\Models\Job::where('status', 'open')
                ->where('budget_max', '>=', 1000)
                ->with(['user:id,first_name,last_name,avatar,average_rating', 'category:id,name,slug'])
                ->orderBy('budget_max', 'desc')
                ->limit(15)
                ->get();
        }, CacheService::MEDIUM_TTL, ['jobs']);
    }

    /**
     * Warm top skills cache
     */
    private function warmTopSkills(CacheService $cacheService): void
    {
        $cacheService->cacheQuery('skills:top_rated', function () {
            return \App\Models\Skill::where('is_available', true)
                ->whereHas('user', function ($query) {
                    $query->where('average_rating', '>=', 4.5);
                })
                ->with(['user:id,first_name,last_name,avatar,average_rating', 'category:id,name,slug'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        }, CacheService::MEDIUM_TTL, ['skills']);

        $cacheService->cacheQuery('skills:recent', function () {
            return \App\Models\Skill::where('is_available', true)
                ->with(['user:id,first_name,last_name,avatar,average_rating', 'category:id,name,slug'])
                ->orderBy('created_at', 'desc')
                ->limit(25)
                ->get();
        }, CacheService::SHORT_TTL, ['skills']);

        $cacheService->cacheQuery('skills:by_category', function () {
            return \App\Models\Category::where('is_active', true)
                ->with(['skills' => function ($query) {
                    $query->where('is_available', true)
                        ->with('user:id,first_name,last_name,avatar,average_rating')
                        ->limit(5);
                }])
                ->get();
        }, CacheService::MEDIUM_TTL, ['skills', 'categories']);
    }

    /**
     * Warm popular searches cache
     */
    private function warmPopularSearches(CacheService $cacheService): void
    {
        // Cache popular search terms (this would typically come from analytics)
        $popularTerms = [
            'web development', 'mobile app', 'logo design', 'content writing',
            'data entry', 'wordpress', 'graphic design', 'seo', 'marketing',
            'translation', 'video editing', 'photography',
        ];

        foreach ($popularTerms as $term) {
            $cacheService->cacheSearchResults($term, [], function () use ($term) {
                $jobs = \App\Models\Job::search($term)
                    ->where('status', 'open')
                    ->with(['user:id,first_name,last_name,avatar', 'category:id,name'])
                    ->take(10)
                    ->get();

                $skills = \App\Models\Skill::search($term)
                    ->where('is_available', true)
                    ->with(['user:id,first_name,last_name,avatar', 'category:id,name'])
                    ->take(10)
                    ->get();

                return [
                    'jobs' => $jobs,
                    'skills' => $skills,
                    'total' => $jobs->count() + $skills->count(),
                ];
            }, CacheService::MEDIUM_TTL);
        }
    }

    /**
     * Warm user statistics cache
     */
    private function warmUserStats(CacheService $cacheService): void
    {
        $cacheService->cacheQuery('stats:platform', function () {
            return [
                'total_users' => \App\Models\User::count(),
                'active_jobs' => \App\Models\Job::where('status', 'open')->count(),
                'available_skills' => \App\Models\Skill::where('is_available', true)->count(),
                'completed_jobs' => \App\Models\Job::where('status', 'completed')->count(),
                'total_reviews' => \App\Models\Review::count(),
                'average_rating' => \App\Models\Review::avg('rating'),
            ];
        }, CacheService::LONG_TTL, ['stats']);

        $cacheService->cacheQuery('stats:recent_activity', function () {
            return [
                'jobs_this_week' => \App\Models\Job::where('created_at', '>=', now()->subWeek())->count(),
                'skills_this_week' => \App\Models\Skill::where('created_at', '>=', now()->subWeek())->count(),
                'users_this_week' => \App\Models\User::where('created_at', '>=', now()->subWeek())->count(),
                'reviews_this_week' => \App\Models\Review::where('created_at', '>=', now()->subWeek())->count(),
            ];
        }, CacheService::SHORT_TTL, ['stats']);
    }
}
