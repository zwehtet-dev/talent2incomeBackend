<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use App\Services\FilterBuilder;

beforeEach(function () {
    // Create test data
    Category::factory()->count(5)->create();

    User::factory()->count(10)->create([
        'location' => 'New York, NY',
        'latitude' => 40.7128,
        'longitude' => -74.0060,
    ]);

    User::factory()->count(5)->create([
        'location' => 'Los Angeles, CA',
        'latitude' => 34.0522,
        'longitude' => -118.2437,
    ]);

    Job::factory()->count(20)->create([
        'status' => Job::STATUS_OPEN,
        'is_urgent' => false,
    ]);

    Job::factory()->count(5)->create([
        'status' => Job::STATUS_OPEN,
        'is_urgent' => true,
    ]);

    Skill::factory()->count(15)->create([
        'is_active' => true,
        'is_available' => true,
        'pricing_type' => 'hourly',
    ]);

    Skill::factory()->count(10)->create([
        'is_active' => true,
        'is_available' => true,
        'pricing_type' => 'fixed',
    ]);
});

test('can create job filter builder', function () {
    $filterBuilder = FilterBuilder::forJobs();

    expect($filterBuilder)->toBeInstanceOf(FilterBuilder::class);
    expect($filterBuilder->getAppliedFilters())->toBeEmpty();
});

test('can create skill filter builder', function () {
    $filterBuilder = FilterBuilder::forSkills();

    expect($filterBuilder)->toBeInstanceOf(FilterBuilder::class);
    expect($filterBuilder->getAppliedFilters())->toBeEmpty();
});

test('can filter jobs by category', function () {
    $category = Category::first();
    $filterBuilder = FilterBuilder::forJobs()->category($category->id);

    $results = $filterBuilder->getQuery()->get();

    expect($results->every(fn ($job) => $job->category_id === $category->id))->toBeTrue();
    expect($filterBuilder->getAppliedFilters())->toHaveKey('category_id');
});

test('can filter jobs by price range', function () {
    $filterBuilder = FilterBuilder::forJobs()->priceRange(100, 500);

    expect($filterBuilder->getAppliedFilters())->toHaveKey('price_range');
});

test('can filter by location text', function () {
    $filterBuilder = FilterBuilder::forJobs()->location('New York');

    $appliedFilters = $filterBuilder->getAppliedFilters();

    expect($appliedFilters)->toHaveKey('location');
    expect($appliedFilters['location']['type'])->toBe('text');
    expect($appliedFilters['location']['value'])->toBe('New York');
});

test('can filter by availability', function () {
    $filterBuilder = FilterBuilder::forJobs()->availability(true);

    $results = $filterBuilder->getQuery()->get();

    expect($results->every(fn ($job) => $job->status === Job::STATUS_OPEN))->toBeTrue();
    expect($filterBuilder->getAppliedFilters())->toHaveKey('availability');
});

test('can chain multiple filters', function () {
    $category = Category::first();
    $filterBuilder = FilterBuilder::forJobs()
        ->category($category->id)
        ->priceRange(100, 500)
        ->location('New York')
        ->urgent(true)
        ->search('web development')
        ->sortBy('relevance');

    $appliedFilters = $filterBuilder->getAppliedFilters();

    expect($appliedFilters)->toHaveKey('category_id');
    expect($appliedFilters)->toHaveKey('price_range');
    expect($appliedFilters)->toHaveKey('location');
    expect($appliedFilters)->toHaveKey('urgent');
    expect($appliedFilters)->toHaveKey('search');
    expect($appliedFilters)->toHaveKey('sort');
});

test('generates consistent filter hash', function () {
    $filterBuilder1 = FilterBuilder::forJobs()
        ->category(1)
        ->priceRange(100, 500);

    $filterBuilder2 = FilterBuilder::forJobs()
        ->category(1)
        ->priceRange(100, 500);

    expect($filterBuilder1->getFilterHash())->toBe($filterBuilder2->getFilterHash());
});
