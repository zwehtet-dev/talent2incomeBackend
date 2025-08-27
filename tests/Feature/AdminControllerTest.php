<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private User $admin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create([
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);
    }

    public function test_dashboard_returns_comprehensive_data_for_admin()
    {
        // Create some test data
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'overview' => [
                        'total_users',
                        'active_users',
                        'total_jobs',
                        'active_jobs',
                        'total_skills',
                        'total_payments',
                        'total_reviews',
                        'platform_revenue',
                    ],
                    'recent_activity' => [
                        'new_users_today',
                        'new_jobs_today',
                        'payments_today',
                        'reviews_today',
                        'recent_registrations',
                    ],
                    'pending_issues',
                    'financial_summary',
                    'user_metrics',
                    'platform_health',
                ],
            ]);
    }

    public function test_dashboard_denies_access_to_non_admin()
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_users_endpoint_returns_paginated_user_data()
    {
        // Create additional users
        User::factory()->count(10)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'full_name',
                            'email',
                            'is_active',
                            'is_admin',
                            'created_at',
                            'jobs_count',
                            'skills_count',
                        ],
                    ],
                    'current_page',
                    'total',
                ],
                'filters' => [
                    'total_users',
                    'active_users',
                    'inactive_users',
                    'admin_users',
                ],
            ]);
    }

    public function test_users_endpoint_supports_filtering()
    {
        // Create users with different statuses
        User::factory()->create(['is_active' => false]);
        User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/users?status=inactive');

        $response->assertStatus(200);

        $users = $response->json('data.data');
        $this->assertCount(1, $users);
        $this->assertFalse($users[0]['is_active']);
    }

    public function test_bulk_user_actions_activate_users()
    {
        $inactiveUsers = User::factory()->count(3)->create(['is_active' => false]);
        $userIds = $inactiveUsers->pluck('id')->toArray();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/users/bulk-actions', [
                'action' => 'activate',
                'user_ids' => $userIds,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Bulk activate completed successfully',
                'affected_count' => 3,
            ]);

        // Verify users are activated
        foreach ($userIds as $userId) {
            $this->assertTrue(User::find($userId)->is_active);
        }
    }

    public function test_bulk_user_actions_validates_input()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/users/bulk-actions', [
                'action' => 'invalid_action',
                'user_ids' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['action', 'user_ids']);
    }

    public function test_bulk_user_actions_prevents_admin_deactivation()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/users/bulk-actions', [
                'action' => 'deactivate',
                'user_ids' => [$adminUser->id],
            ]);

        $response->assertStatus(200)
            ->assertJson(['affected_count' => 0]);

        // Verify admin is still active
        $this->assertTrue($adminUser->fresh()->is_active);
    }

    public function test_content_moderation_returns_flagged_content()
    {
        // Create flagged review
        $review = Review::factory()->create([
            'is_flagged' => true,
            'moderated_at' => null,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/content-moderation?type=reviews');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'reviews' => [
                        'data' => [
                            '*' => [
                                'id',
                                'rating',
                                'comment',
                                'is_flagged',
                                'reviewer',
                                'reviewee',
                                'job',
                            ],
                        ],
                    ],
                ],
                'statistics' => [
                    'pending_reviews',
                    'total_pending',
                ],
            ]);
    }

    public function test_moderate_content_approves_review()
    {
        $review = Review::factory()->create([
            'is_flagged' => true,
            'moderated_at' => null,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/moderate-content', [
                'content_type' => 'review',
                'content_id' => $review->id,
                'action' => 'approve',
                'reason' => 'Content is appropriate',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Content approved successfully',
                'action' => 'approve',
                'content_type' => 'review',
                'content_id' => $review->id,
            ]);

        // Verify review is approved
        $review->refresh();
        $this->assertTrue($review->is_approved);
        $this->assertNotNull($review->moderated_at);
        $this->assertSame($this->admin->id, $review->moderated_by);
    }

    public function test_moderate_content_validates_flagged_content()
    {
        $review = Review::factory()->create([
            'is_flagged' => false, // Not flagged
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/moderate-content', [
                'content_type' => 'review',
                'content_id' => $review->id,
                'action' => 'approve',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content_id']);
    }

    public function test_disputes_returns_disputed_payments()
    {
        $payment = Payment::factory()->create([
            'status' => 'disputed',
            'dispute_reason' => 'Work not completed',
            'dispute_created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/disputes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'job',
                            'amount',
                            'payer',
                            'payee',
                            'dispute' => [
                                'reason',
                                'created_at',
                            ],
                        ],
                    ],
                ],
                'statistics' => [
                    'total_disputes',
                    'open_disputes',
                    'resolved_disputes',
                ],
            ]);
    }

    public function test_resolve_dispute_processes_full_refund()
    {
        $payment = Payment::factory()->create([
            'status' => 'disputed',
            'amount' => 100.00,
            'dispute_reason' => 'Work not completed',
            'dispute_created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/disputes/{$payment->id}/resolve", [
                'resolution' => 'refund_full',
                'resolution_notes' => 'Work was not completed as agreed',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Dispute resolved successfully',
                'resolution' => 'refund_full',
                'payment_id' => $payment->id,
            ]);

        // Verify payment is updated
        $payment->refresh();
        $this->assertSame('refunded', $payment->status);
        $this->assertSame(100.00, $payment->refund_amount);
        $this->assertNotNull($payment->dispute_resolved_at);
        $this->assertSame($this->admin->id, $payment->dispute_resolved_by);
    }

    public function test_resolve_dispute_validates_partial_amounts()
    {
        $payment = Payment::factory()->create([
            'status' => 'disputed',
            'amount' => 100.00,
            'dispute_created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/disputes/{$payment->id}/resolve", [
                'resolution' => 'refund_partial',
                'resolution_notes' => 'Partial refund justified',
                'refund_amount' => 150.00, // More than payment amount
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['refund_amount']);
    }

    public function test_system_health_returns_health_metrics()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/system-health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'overall_status',
                'health_score',
                'data' => [
                    'database',
                    'cache',
                    'queue',
                    'storage',
                    'api_performance',
                    'error_rates',
                    'active_sessions',
                    'system_load',
                ],
                'alerts',
                'last_updated',
            ]);
    }

    public function test_audit_log_returns_activity_data()
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/audit-log');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'log_entries',
                    'user_actions',
                    'payment_actions',
                    'moderation_actions',
                    'summary' => [
                        'total_entries',
                        'error_count',
                        'warning_count',
                        'info_count',
                    ],
                ],
            ]);
    }

    public function test_admin_endpoints_require_authentication()
    {
        $endpoints = [
            'GET /api/admin/dashboard',
            'GET /api/admin/users',
            'GET /api/admin/content-moderation',
            'GET /api/admin/disputes',
            'GET /api/admin/system-health',
            'GET /api/admin/audit-log',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $url] = explode(' ', $endpoint);

            $response = $this->json($method, $url);
            $response->assertStatus(401);
        }
    }

    public function test_admin_endpoints_require_admin_privileges()
    {
        $endpoints = [
            'GET /api/admin/dashboard',
            'GET /api/admin/users',
            'GET /api/admin/content-moderation',
            'GET /api/admin/disputes',
            'GET /api/admin/system-health',
            'GET /api/admin/audit-log',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $url] = explode(' ', $endpoint);

            $response = $this->actingAs($this->regularUser, 'sanctum')
                ->json($method, $url);
            $response->assertStatus(403);
        }
    }
}
