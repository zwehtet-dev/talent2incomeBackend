<?php

namespace App\Services;

use App\Models\CohortAnalytics;
use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\RevenueAnalytics;
use App\Models\Review;
use App\Models\Skill;
use App\Models\SystemPerformanceMetrics;
use App\Models\User;
use App\Models\UserEngagementAnalytics;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Calculate and store revenue analytics for a specific date
     */
    public function calculateRevenueAnalytics(Carbon $date): RevenueAnalytics
    {
        $payments = Payment::whereDate('created_at', $date)
            ->where('status', 'released')
            ->get();

        $totalRevenue = $payments->sum('amount');
        $platformFees = $payments->sum('platform_fee');
        $netRevenue = $totalRevenue - $platformFees;
        $transactionCount = $payments->count();
        $averageTransactionValue = $transactionCount > 0 ? $totalRevenue / $transactionCount : 0;

        return RevenueAnalytics::updateOrCreate(
            ['date' => $date],
            [
                'total_revenue' => $totalRevenue,
                'platform_fees' => $platformFees,
                'net_revenue' => $netRevenue,
                'transaction_count' => $transactionCount,
                'average_transaction_value' => $averageTransactionValue,
            ]
        );
    }

    /**
     * Calculate and store user engagement analytics for a specific date
     */
    public function calculateUserEngagementAnalytics(Carbon $date): UserEngagementAnalytics
    {
        $dailyActiveUsers = User::whereDate('last_activity_at', $date)->count();
        $weeklyActiveUsers = User::whereBetween('last_activity_at', [
            $date->copy()->subDays(6),
            $date,
        ])->count();
        $monthlyActiveUsers = User::whereBetween('last_activity_at', [
            $date->copy()->subDays(29),
            $date,
        ])->count();

        $newRegistrations = User::whereDate('created_at', $date)->count();
        $jobsPosted = Job::whereDate('created_at', $date)->count();
        $skillsPosted = Skill::whereDate('created_at', $date)->count();
        $messagesSent = Message::whereDate('created_at', $date)->count();
        $reviewsCreated = Review::whereDate('created_at', $date)->count();

        // Calculate average session duration (placeholder - would need session tracking)
        $averageSessionDuration = 0;

        return UserEngagementAnalytics::updateOrCreate(
            ['date' => $date],
            [
                'daily_active_users' => $dailyActiveUsers,
                'weekly_active_users' => $weeklyActiveUsers,
                'monthly_active_users' => $monthlyActiveUsers,
                'new_registrations' => $newRegistrations,
                'jobs_posted' => $jobsPosted,
                'skills_posted' => $skillsPosted,
                'messages_sent' => $messagesSent,
                'reviews_created' => $reviewsCreated,
                'average_session_duration' => $averageSessionDuration,
            ]
        );
    }

    /**
     * Calculate cohort analytics for user retention
     */
    public function calculateCohortAnalytics(Carbon $cohortMonth, int $periodNumber): CohortAnalytics
    {
        $cohortUsers = User::whereYear('created_at', $cohortMonth->year)
            ->whereMonth('created_at', $cohortMonth->month)
            ->pluck('id');

        $usersCount = $cohortUsers->count();

        if ($usersCount === 0) {
            return CohortAnalytics::updateOrCreate(
                ['cohort_month' => $cohortMonth, 'period_number' => $periodNumber],
                ['users_count' => 0, 'retention_rate' => 0, 'revenue_per_user' => 0]
            );
        }

        $periodStart = $cohortMonth->copy()->addMonths($periodNumber);
        $periodEnd = $periodStart->copy()->endOfMonth();

        $activeUsers = User::whereIn('id', $cohortUsers)
            ->whereBetween('last_activity_at', [$periodStart, $periodEnd])
            ->count();

        $retentionRate = ($activeUsers / $usersCount) * 100;

        $revenue = Payment::whereIn('payer_id', $cohortUsers)
            ->orWhereIn('payee_id', $cohortUsers)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->where('status', 'released')
            ->sum('amount');

        $revenuePerUser = $activeUsers > 0 ? $revenue / $activeUsers : 0;

        return CohortAnalytics::updateOrCreate(
            ['cohort_month' => $cohortMonth, 'period_number' => $periodNumber],
            [
                'users_count' => $usersCount,
                'retention_rate' => $retentionRate,
                'revenue_per_user' => $revenuePerUser,
            ]
        );
    }

    /**
     * Record system performance metrics
     */
    public function recordSystemPerformanceMetrics(array $metrics): SystemPerformanceMetrics
    {
        return SystemPerformanceMetrics::create([
            'recorded_at' => now(),
            'average_response_time' => $metrics['average_response_time'] ?? 0,
            'total_requests' => $metrics['total_requests'] ?? 0,
            'error_count' => $metrics['error_count'] ?? 0,
            'error_rate' => $metrics['error_rate'] ?? 0,
            'cpu_usage' => $metrics['cpu_usage'] ?? 0,
            'memory_usage' => $metrics['memory_usage'] ?? 0,
            'disk_usage' => $metrics['disk_usage'] ?? 0,
            'active_connections' => $metrics['active_connections'] ?? 0,
        ]);
    }

    /**
     * Get revenue trends for a date range
     */
    public function getRevenueTrends(Carbon $startDate, Carbon $endDate): array
    {
        $analytics = RevenueAnalytics::forDateRange($startDate, $endDate)
            ->orderBy('date')
            ->get();

        $trends = [
            'total_revenue' => $analytics->sum('total_revenue'),
            'platform_fees' => $analytics->sum('platform_fees'),
            'net_revenue' => $analytics->sum('net_revenue'),
            'total_transactions' => $analytics->sum('transaction_count'),
            'average_transaction_value' => $analytics->avg('average_transaction_value'),
            'daily_data' => $analytics->map(function ($item) {
                return [
                    'date' => $item->date->format('Y-m-d'),
                    'revenue' => $item->total_revenue,
                    'transactions' => $item->transaction_count,
                ];
            }),
        ];

        // Calculate growth rates
        $previousPeriod = $this->getPreviousPeriodRevenue($startDate, $endDate);
        if ($previousPeriod > 0) {
            $trends['growth_rate'] = (($trends['total_revenue'] - $previousPeriod) / $previousPeriod) * 100;
        } else {
            $trends['growth_rate'] = 0;
        }

        return $trends;
    }

    /**
     * Get user engagement trends
     */
    public function getUserEngagementTrends(Carbon $startDate, Carbon $endDate): array
    {
        $analytics = UserEngagementAnalytics::forDateRange($startDate, $endDate)
            ->orderBy('date')
            ->get();

        return [
            'average_daily_active_users' => $analytics->avg('daily_active_users'),
            'total_new_registrations' => $analytics->sum('new_registrations'),
            'total_jobs_posted' => $analytics->sum('jobs_posted'),
            'total_skills_posted' => $analytics->sum('skills_posted'),
            'total_messages_sent' => $analytics->sum('messages_sent'),
            'total_reviews_created' => $analytics->sum('reviews_created'),
            'daily_data' => $analytics->map(function ($item) {
                return [
                    'date' => $item->date->format('Y-m-d'),
                    'active_users' => $item->daily_active_users,
                    'new_registrations' => $item->new_registrations,
                    'jobs_posted' => $item->jobs_posted,
                ];
            }),
        ];
    }

    /**
     * Get cohort analysis data
     */
    public function getCohortAnalysis(int $months = 12): array
    {
        $cohorts = CohortAnalytics::where('cohort_month', '>=', now()->subMonths($months))
            ->orderBy('cohort_month')
            ->orderBy('period_number')
            ->get()
            ->groupBy('cohort_month');

        $analysis = [];
        foreach ($cohorts as $cohortMonth => $periods) {
            $analysis[$cohortMonth] = [
                'cohort_size' => $periods->first()->users_count,
                'periods' => $periods->map(function ($period) {
                    return [
                        'period' => $period->period_number,
                        'retention_rate' => $period->retention_rate,
                        'revenue_per_user' => $period->revenue_per_user,
                    ];
                }),
            ];
        }

        return $analysis;
    }

    /**
     * Get system health indicators
     */
    public function getSystemHealthIndicators(): array
    {
        $latestMetrics = SystemPerformanceMetrics::latest('recorded_at')->first();
        $last24Hours = SystemPerformanceMetrics::latest(24)->get();

        return [
            'current' => [
                'response_time' => $latestMetrics->average_response_time ?? 0,
                'error_rate' => $latestMetrics->error_rate ?? 0,
                'cpu_usage' => $latestMetrics->cpu_usage ?? 0,
                'memory_usage' => $latestMetrics->memory_usage ?? 0,
                'disk_usage' => $latestMetrics->disk_usage ?? 0,
                'active_connections' => $latestMetrics->active_connections ?? 0,
            ],
            'trends' => [
                'average_response_time' => $last24Hours->avg('average_response_time'),
                'average_error_rate' => $last24Hours->avg('error_rate'),
                'peak_cpu_usage' => $last24Hours->max('cpu_usage'),
                'peak_memory_usage' => $last24Hours->max('memory_usage'),
            ],
            'alerts' => $this->generateHealthAlerts($latestMetrics),
        ];
    }

    /**
     * Generate comprehensive dashboard data
     */
    public function getDashboardData(Carbon $startDate, Carbon $endDate): array
    {
        return [
            'revenue_trends' => $this->getRevenueTrends($startDate, $endDate),
            'engagement_trends' => $this->getUserEngagementTrends($startDate, $endDate),
            'cohort_analysis' => $this->getCohortAnalysis(),
            'system_health' => $this->getSystemHealthIndicators(),
            'key_metrics' => $this->getKeyMetrics($startDate, $endDate),
        ];
    }

    /**
     * Get key performance indicators
     */
    private function getKeyMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('last_activity_at', '>=', $startDate)->count();
        $totalJobs = Job::count();
        $completedJobs = Job::where('status', 'completed')->count();
        $totalRevenue = Payment::where('status', 'released')->sum('amount');

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'user_activity_rate' => $totalUsers > 0 ? ($activeUsers / $totalUsers) * 100 : 0,
            'total_jobs' => $totalJobs,
            'completed_jobs' => $completedJobs,
            'job_completion_rate' => $totalJobs > 0 ? ($completedJobs / $totalJobs) * 100 : 0,
            'total_revenue' => $totalRevenue,
            'average_job_value' => $completedJobs > 0 ? $totalRevenue / $completedJobs : 0,
        ];
    }

    /**
     * Get previous period revenue for growth calculation
     */
    private function getPreviousPeriodRevenue(Carbon $startDate, Carbon $endDate): float
    {
        $periodLength = $startDate->diffInDays($endDate);
        $previousStart = $startDate->copy()->subDays($periodLength + 1);
        $previousEnd = $startDate->copy()->subDay();

        return RevenueAnalytics::forDateRange($previousStart, $previousEnd)
            ->sum('total_revenue');
    }

    /**
     * Generate health alerts based on current metrics
     * @param mixed $metrics
     */
    private function generateHealthAlerts($metrics): array
    {
        $alerts = [];

        if ($metrics) {
            if ($metrics->error_rate > 5) {
                $alerts[] = [
                    'type' => 'error',
                    'message' => 'High error rate detected: ' . (int)$metrics->error_rate . '%',
                ];
            }

            if ($metrics->average_response_time > 2000) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => 'Slow response time: ' . (int)$metrics->average_response_time . 'ms',
                ];
            }

            if ($metrics->cpu_usage > 80) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => 'High CPU usage: ' . (int)$metrics->cpu_usage . '%',
                ];
            }

            if ($metrics->memory_usage > 85) {
                $alerts[] = [
                    'type' => 'error',
                    'message' => 'High memory usage: ' . (int)$metrics->memory_usage . '%',
                ];
            }

            if ($metrics->disk_usage > 90) {
                $alerts[] = [
                    'type' => 'critical',
                    'message' => 'Critical disk usage: ' . (int)$metrics->disk_usage . '%',
                ];
            }
        }

        return $alerts;
    }
}
