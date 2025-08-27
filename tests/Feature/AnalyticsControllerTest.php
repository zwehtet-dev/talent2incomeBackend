<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Payment;
use App\Models\RevenueAnalytics;
use App\Models\ScheduledReport;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserEngagementAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['is_admin' => true]);
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    public function test_dashboard_returns_comprehensive_analytics_data()
    {
        // Create test data
        $this->createTestAnalyticsData();

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/analytics/dashboard?' . http_build_query([
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'revenue_trends',
                    'engagement_trends',
                    'cohort_analysis',
                    'system_health',
                    'key_metrics',
                ],
            ]);
    }

    public function test_revenue_analytics_returns_trend_data()
    {
        RevenueAnalytics::factory()->create([
            'date' => now()->subDay(),
            'total_revenue' => 1000.00,
            'platform_fees' => 100.00,
            'transaction_count' => 10,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/analytics/revenue?' . http_build_query([
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_revenue',
                    'platform_fees',
                    'net_revenue',
                    'total_transactions',
                    'average_transaction_value',
                    'daily_data',
                    'growth_rate',
                ],
            ]);
    }

    public function test_engagement_analytics_returns_user_metrics()
    {
        UserEngagementAnalytics::factory()->create([
            'date' => now()->subDay(),
            'daily_active_users' => 50,
            'new_registrations' => 5,
            'jobs_posted' => 10,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/analytics/engagement?' . http_build_query([
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'average_daily_active_users',
                    'total_new_registrations',
                    'total_jobs_posted',
                    'total_skills_posted',
                    'total_messages_sent',
                    'total_reviews_created',
                    'daily_data',
                ],
            ]);
    }

    public function test_generate_report_creates_custom_report()
    {
        $this->createTestAnalyticsData();

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/analytics/generate-report', [
                'type' => 'custom',
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'metrics' => ['revenue_analytics', 'user_engagement'],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'report_id',
                    'name',
                    'type',
                    'report_date',
                    'generated_at',
                    'data',
                ],
            ]);

        $this->assertDatabaseHas('generated_reports', [
            'type' => 'custom',
        ]);
    }

    public function test_create_scheduled_report_stores_configuration()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/analytics/scheduled-reports', [
                'name' => 'Weekly Revenue Report',
                'type' => 'weekly',
                'recipients' => ['admin@example.com', 'manager@example.com'],
                'metrics' => ['revenue_analytics', 'user_engagement'],
                'frequency' => 'weekly',
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'type',
                    'recipients',
                    'metrics',
                    'frequency',
                    'is_active',
                    'next_send_at',
                ],
            ]);

        $this->assertDatabaseHas('scheduled_reports', [
            'name' => 'Weekly Revenue Report',
            'frequency' => 'weekly',
        ]);
    }

    public function test_update_scheduled_report_modifies_configuration()
    {
        $scheduledReport = ScheduledReport::factory()->create([
            'name' => 'Test Report',
            'frequency' => 'daily',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->putJson("/api/admin/analytics/scheduled-reports/{$scheduledReport->id}", [
                'name' => 'Updated Test Report',
                'frequency' => 'weekly',
                'is_active' => false,
            ]);

        $response->assertOk();

        $scheduledReport->refresh();
        $this->assertSame('Updated Test Report', $scheduledReport->name);
        $this->assertSame('weekly', $scheduledReport->frequency);
        $this->assertFalse($scheduledReport->is_active);
    }

    public function test_delete_scheduled_report_removes_configuration()
    {
        $scheduledReport = ScheduledReport::factory()->create();

        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/admin/analytics/scheduled-reports/{$scheduledReport->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('scheduled_reports', [
            'id' => $scheduledReport->id,
        ]);
    }

    public function test_record_performance_metrics_stores_system_data()
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/analytics/performance-metrics', [
                'average_response_time' => 150.5,
                'total_requests' => 1000,
                'error_count' => 5,
                'error_rate' => 0.5,
                'cpu_usage' => 45.2,
                'memory_usage' => 67.8,
                'disk_usage' => 23.1,
                'active_connections' => 25,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'recorded_at',
                    'average_response_time',
                    'total_requests',
                    'error_count',
                    'cpu_usage',
                ],
            ]);

        $this->assertDatabaseHas('system_performance_metrics', [
            'average_response_time' => 150.5,
            'total_requests' => 1000,
            'error_count' => 5,
        ]);
    }

    public function test_calculate_analytics_processes_data_for_date()
    {
        // Create test data for calculation
        Payment::factory()->create([
            'amount' => 100.00,
            'platform_fee' => 10.00,
            'status' => 'released',
            'created_at' => now()->subDay(),
        ]);

        User::factory()->create([
            'created_at' => now()->subDay(),
            'last_activity_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/analytics/calculate-analytics', [
                'date' => now()->subDay()->format('Y-m-d'),
                'types' => ['revenue', 'engagement'],
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'revenue',
                    'engagement',
                ],
            ]);

        $this->assertDatabaseHas('revenue_analytics', [
            'total_revenue' => 100.00,
        ]);

        $this->assertDatabaseHas('user_engagement_analytics', [
            'new_registrations' => 1,
        ]);
    }

    public function test_regular_user_cannot_access_analytics()
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/admin/analytics/dashboard?' . http_build_query([
                'start_date' => now()->subDays(7)->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
            ]));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_analytics()
    {
        $response = $this->getJson('/api/admin/analytics/dashboard?' . http_build_query([
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]));

        $response->assertUnauthorized();
    }

    public function test_invalid_date_range_returns_validation_error()
    {
        $token = $this->adminUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])
            ->getJson('/api/admin/analytics/dashboard?' . http_build_query([
                'start_date' => now()->addDay()->format('Y-m-d'), // Future date
                'end_date' => now()->format('Y-m-d'),
            ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_system_health_returns_current_metrics()
    {
        // Create some performance metrics
        \App\Models\SystemPerformanceMetrics::factory()->create([
            'recorded_at' => now(),
            'average_response_time' => 200.0,
            'error_rate' => 2.5,
            'cpu_usage' => 65.0,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/analytics/system-health');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current' => [
                        'response_time',
                        'error_rate',
                        'cpu_usage',
                        'memory_usage',
                    ],
                    'trends',
                    'alerts',
                ],
            ]);
    }

    protected function createTestAnalyticsData(): void
    {
        // Create revenue analytics with unique dates
        for ($i = 1; $i <= 7; $i++) {
            RevenueAnalytics::factory()->create([
                'date' => now()->subDays($i),
            ]);
        }

        // Create engagement analytics with unique dates
        for ($i = 1; $i <= 7; $i++) {
            UserEngagementAnalytics::factory()->create([
                'date' => now()->subDays($i),
            ]);
        }

        // Create some actual data for calculations
        Payment::factory()->count(5)->create([
            'status' => 'released',
            'created_at' => now()->subDays(rand(1, 7)),
        ]);

        User::factory()->count(10)->create([
            'created_at' => now()->subDays(rand(1, 7)),
            'last_activity_at' => now()->subDays(rand(0, 3)),
        ]);

        Job::factory()->count(8)->create([
            'created_at' => now()->subDays(rand(1, 7)),
        ]);

        Skill::factory()->count(6)->create([
            'created_at' => now()->subDays(rand(1, 7)),
        ]);
    }
}
