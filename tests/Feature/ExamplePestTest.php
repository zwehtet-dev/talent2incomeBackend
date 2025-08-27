<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Application Health', function () {
    it('returns a successful response for welcome page', function () {
        $response = $this->get('/');

        $response->assertStatus(200);
    });
});

describe('Database Connection', function () {
    it('can connect to the test database', function () {
        expect(DB::connection()->getPdo())->not->toBeNull();
    });

    it('uses SQLite in-memory database for testing', function () {
        expect(DB::connection()->getDriverName())->toBe('sqlite');
        expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    });
});

describe('Environment Configuration', function () {
    it('is running in testing environment', function () {
        expect(app()->environment())->toBe('testing');
    });

    it('has proper testing configuration', function () {
        expect(config('app.env'))->toBe('testing');
        expect(config('cache.default'))->toBe('array');
        expect(config('session.driver'))->toBe('array');
        expect(config('queue.default'))->toBe('sync');
    });
});
