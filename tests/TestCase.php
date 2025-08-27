<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we're using the testing environment
        $this->app['env'] = 'testing';

        // Set up the database
        $this->setUpDatabase();

        // Set up faker locale
        $this->setUpFaker();
    }

    /**
     * Clean up after each test.
     */
    protected function tearDown(): void
    {
        // Clear any cached data
        if (method_exists($this, 'artisan')) {
            Artisan::call('cache:clear');
        }

        parent::tearDown();
    }

    /**
     * Create application.
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Set up the database for testing.
     */
    protected function setUpDatabase(): void
    {
        // Enable foreign key constraints for SQLite
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=ON');
        }
    }

    /**
     * Assert that a response has a specific JSON structure.
     * @param mixed $response
     */
    protected function assertJsonStructure(array $structure, $response): void
    {
        $response->assertJsonStructure($structure);
    }

    /**
     * Assert that a response contains validation errors.
     * @param mixed $response
     */
    protected function assertValidationErrors(array $fields, $response): void
    {
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($fields);
    }

    /**
     * Create a user for testing.
     */
    protected function createUser(array $attributes = []): \App\Models\User
    {
        return \App\Models\User::factory()->create($attributes);
    }

    /**
     * Act as a specific user for the test.
     */
    protected function actingAsUser(\App\Models\User $user = null): self
    {
        $user = $user ?: $this->createUser();

        return $this->actingAs($user, 'sanctum');
    }

    /**
     * Create an admin user for testing.
     */
    protected function createAdminUser(array $attributes = []): \App\Models\User
    {
        return $this->createUser(array_merge(['is_admin' => true], $attributes));
    }

    /**
     * Act as an admin user for the test.
     */
    protected function actingAsAdmin(\App\Models\User $admin = null): self
    {
        $admin = $admin ?: $this->createAdminUser();

        return $this->actingAs($admin, 'sanctum');
    }
}
