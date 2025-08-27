<?php

namespace Tests\Unit;

use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\RevenueAnalytics;
use App\Models\Review;
use App\Models\Skill;
use App\Models\SystemPerformanceMetrics;
use App\Models\User;
use App\Models\UserEngagementAnalytics;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsService $analyticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyticsService = app(AnalyticsService::class);
    }

    public function test_calculate_revenue_analytics_processes_payments_correctly()
    {
        $date = Carbon::yesterday();

        // Create test payments
        Payment::factory()->create([
            'amount' => 100.00,
            'platform_fee' => 10.00,
            'status' => 'released',
            'created_at' => $date,
        ]);

        Payment::factory()->create([
            'amount' => 200.00,
            'platform_fee' => 20.00,
            'status' => 'released',
            'created_at' => $date,
        ]);

        // Create a payment that shouldn't be included (different status)
        Payment::factory()->create([
            'amount' => 50.00,
            'platform_fee' => 5.00,
            'status' => 'pending',
            'created_at' => $date,
        ]);

        $result = $this->analyticsService->calculateRevenueAnalytics($date);

        $this->assertSame(300.00, $result->total_revenue);
        $this->assertSame(30.00, $result->platform_fees);
        $this->assertSame(270.00, $result->net_revenue);
        $this->assertSame(2, $result->transaction_count);
        $this->assertSame(150.00, $result->average_transaction_value);
        $this->assertSame($date->format('Y-m-d'), $result->date->format('Y-m-d'));
    }

    public function test_calculate_user_engagement_analytics_counts_activities()
    {
        $date = Carbon::yesterday();

        // Create users with different activity patterns
        User::factory()->create([
            'created_at' => $date,
            'last_activity_at' => $date,
        ]);

        User::factory()->create([
            'created_at' => $date->copy()->subDays(5),
            'last_activity_at' => $date,
        ]);

        User::factory()->create([
            'created_at' => $date->copy()->subDays(10),
            'last_activity_at' => $date->copy()->subDays(2), // Not active on target date
        ]);

        // Create activities for the date
        Job::factory()->count(3)->create(['created_at' => $date]);
        Skill::factory()->count(2)->create(['created_at' => $date]);
        Message::factory()->count(5)->create(['created_at' => $date]);
        Review::factory()->count(1)->create(['created_at' => $date]);

        $result = $this->analyticsService->calculateUserEngagementAnalytics($date);

        $this->assertSame(2, $result->daily_active_users); // Only 2 users active on target date
        $this->assertSame(1, $result->new_registrations); // Only 1 user created on target date
        $this->assertSame(3, $result->jobs_posted);
        $this->assertSame(2, $result->skills_posted);
        $this->assertSame(5, $result->messages_sent);
        $this->assertSame(1, $result->reviews_created);
    }

    public function test_calculate_cohort_analytics_computes_retention_correctly()
    {
        $cohortMonth = Carbon::create(2024, 1, 1); // January 2024
        $periodNumber = 1; // February 2024 (1 month later)

        // Create cohort users (registered in January 2024)
        $cohortUsers = User::factory()->count(10)->create([
            'created_at' => $cohortMonth->copy()->addDays(rand(0, 30)),
        ]);

        // Create some users from different cohorts (should not be counted)
        User::factory()->count(5)->create([
            'created_at' => $cohortMonth->copy()->addMonth(),
        ]);

        // Set activity for some cohort users in the target period (February 2024)
        $activeUsers = $cohortUsers->take(6);
        foreach ($activeUsers as $user) {
            $user->update([
                'last_activity_at' => $cohortMonth->copy()->addMonth()->addDays(rand(1, 28)),
            ]);
        }

        // Create some payments for active users
        foreach ($activeUsers->take(3) as $user) {
            Payment::factory()->create([
                'payer_id' => $user->id,
                'amount' => 100.00,
                'status' => 'released',
                'created_at' => $cohortMonth->copy()->addMonth()->addDays(rand(1, 28)),
            ]);
        }

        $result = $this->analyticsService->calculateCohortAnalytics($cohortMonth, $periodNumber);

        $this->assertSame(10, $result->users_count); // Total cohort size
        $this->assertSame(60.0, $result->retention_rate); // 6/10 * 100
        $this->assertSame(50.0, $result->revenue_per_user); // 300 total revenue / 6 active users
    }

    public function test_record_system_performance_metrics_stores_data()
    {
        $metrics = [
            'average_response_time' => 150.5,
            'total_requests' => 1000,
            'error_count' => 5,
            'error_rate' => 0.5,
            'cpu_usage' => 45.2,
            'memory_usage' => 67.8,
            'disk_usage' => 23.1,
            'active_connections' => 25,
        ];

        $result = $this->analyticsService->recordSystemPerformanceMetrics($metrics);

        $this->assertInstanceOf(SystemPerformanceMetrics::class, $result);
        $this->assertSame(150.5, $result->average_response_time);
        $this->assertSame(1000, $result->total_requests);
        $this->assertSame(5, $result->error_count);
        $this->assertSame(0.5, $result->error_rate);
        $this->assertSame(45.2, $result->cpu_usage);
        $this->assertSame(67.8, $result->memory_usage);
        $this->assertSame(23.1, $result->disk_usage);
        $this->assertSame(25, $result->active_connections);
    }

    public function test_get_revenue_trends_calculates_growth_rate()
    {
        $startDate = Carbon::yesterday()->subDays(6);
        $endDate = Carbon::yesterday();

        // Create current period data
        RevenueAnalytics::factory()->create([
            'date' => $endDate,
            'total_revenue' => 1000.00,
            'transaction_count' => 10,
        ]);

        // Create previous period data for growth calculation
        $previousDate = $startDate->copy()->subDays(7);
        RevenueAnalytics::factory()->create([
            'date' => $previousDate,
            'total_revenue' => 800.00,
            'transaction_count' => 8,
        ]);

        $result = $this->analyticsService->getRevenueTrends($startDate, $endDate);

        $this->assertSame(1000.00, $result['total_revenue']);
        $this->assertSame(10, $result['total_transactions']);
        $this->assertSame(25.0, $result['growth_rate']); // (1000-800)/800 * 100
        $this->assertArrayHasKey('daily_data', $result);
    }

    public function test_get_system_health_indicators_generates_alerts()
    {
        // Create metrics that should trigger alerts
        SystemPerformanceMetrics::factory()->create([
            'recorded_at' => now(),
            'average_response_time' => 3000, // Should trigger slow response alert
            'error_rate' => 10.0, // Should trigger high error rate alert
            'cpu_usage' => 85.0, // Should trigger high CPU alert
            'memory_usage' => 90.0, // Should trigger high memory alert
            'disk_usage' => 95.0, // Should trigger critical disk alert
        ]);

        $result = $this->analyticsService->getSystemHealthIndicators();

        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('trends', $result);
        $this->assertArrayHasKey('alerts', $result);

        $alerts = $result['alerts'];
        $this->assertGreaterThan(0, count($alerts));

        // Check for specific alert types
        $alertMessages = collect($alerts)->pluck('message')->toArray();
        $this->assertContains('Slow response time: 3000ms', $alertMessages);
        $this->assertContains('High error rate detected: 10%', $alertMessages);
        $this->assertContains('High CPU usage: 85%', $alertMessages);
        $this->assertContains('High memory usage: 90%', $alertMessages);
        $this->assertContains('Critical disk usage: 95%', $alertMessages);
    }

    public function test_get_dashboard_data_returns_comprehensive_metrics()
    {
        $startDate = Carbon::yesterday()->subDays(6);
        $endDate = Carbon::yesterday();

        // Create test data
        RevenueAnalytics::factory()->create(['date' => $endDate]);
        UserEngagementAnalytics::factory()->create(['date' => $endDate]);
        SystemPerformanceMetrics::factory()->create(['recorded_at' => now()]);

        // Create some real data for key metrics
        User::factory()->count(5)->create();
        Job::factory()->count(3)->create(['status' => 'completed']);
        Payment::factory()->count(2)->create(['status' => 'released']);

        $result = $this->analyticsService->getDashboardData($startDate, $endDate);

        $this->assertArrayHasKey('revenue_trends', $result);
        $this->assertArrayHasKey('engagement_trends', $result);
        $this->assertArrayHasKey('cohort_analysis', $result);
        $this->assertArrayHasKey('system_health', $result);
        $this->assertArrayHasKey('key_metrics', $result);

        // Verify key metrics structure
        $keyMetrics = $result['key_metrics'];
        $this->assertArrayHasKey('total_users', $keyMetrics);
        $this->assertArrayHasKey('active_users', $keyMetrics);
        $this->assertArrayHasKey('total_jobs', $keyMetrics);
        $this->assertArrayHasKey('completed_jobs', $keyMetrics);
        $this->assertArrayHasKey('total_revenue', $keyMetrics);
    }

    public function test_cohort_analytics_handles_empty_cohort()
    {
        $cohortMonth = Carbon::create(2024, 1, 1);
        $periodNumber = 1;

        // Don't create any users for this cohort

        $result = $this->analyticsService->calculateCohortAnalytics($cohortMonth, $periodNumber);

        $this->assertSame(0, $result->users_count);
        $this->assertSame(0, $result->retention_rate);
        $this->assertSame(0, $result->revenue_per_user);
    }

    public function test_revenue_analytics_handles_no_transactions()
    {
        $date = Carbon::yesterday();

        // Don't create any payments

        $result = $this->analyticsService->calculateRevenueAnalytics($date);

        $this->assertSame(0, $result->total_revenue);
        $this->assertSame(0, $result->platform_fees);
        $this->assertSame(0, $result->net_revenue);
        $this->assertSame(0, $result->transaction_count);
        $this->assertSame(0, $result->average_transaction_value);
    }
}
