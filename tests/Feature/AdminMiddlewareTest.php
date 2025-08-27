<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_middleware_allows_admin_users()
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        // Create a test route that uses admin middleware
        $this->app['router']->get('/test-admin', function () {
            return response()->json(['message' => 'Admin access granted']);
        })->middleware(['auth:sanctum', 'admin']);

        $response = $this->getJson('/test-admin');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Admin access granted']);
    }

    /** @test */
    public function admin_middleware_denies_regular_users()
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        // Create a test route that uses admin middleware
        $this->app['router']->get('/test-admin', function () {
            return response()->json(['message' => 'Admin access granted']);
        })->middleware(['auth:sanctum', 'admin']);

        $response = $this->getJson('/test-admin');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied. Admin privileges required.',
                'status_code' => 403,
            ]);
    }

    /** @test */
    public function admin_middleware_denies_unauthenticated_users()
    {
        // Create a test route that uses admin middleware
        $this->app['router']->get('/test-admin', function () {
            return response()->json(['message' => 'Admin access granted']);
        })->middleware(['auth:sanctum', 'admin']);

        $response = $this->getJson('/test-admin');

        $response->assertStatus(401); // Unauthenticated
    }
}
