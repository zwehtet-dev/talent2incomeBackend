<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Job;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_be_created_with_valid_data()
    {
        $categoryData = [
            'name' => 'Web Development',
            'slug' => 'web-development',
            'description' => 'All web development related services',
            'icon' => 'fas fa-code',
            'is_active' => true,
        ];

        $category = Category::create($categoryData);

        $this->assertInstanceOf(Category::class, $category);
        $this->assertSame('Web Development', $category->name);
        $this->assertSame('web-development', $category->slug);
        $this->assertTrue($category->is_active);
    }

    public function test_category_has_many_jobs()
    {
        $category = Category::factory()->create();
        $jobs = Job::factory()->count(3)->create(['category_id' => $category->id]);

        $this->assertCount(3, $category->jobs);
        $this->assertInstanceOf(Job::class, $category->jobs->first());
    }

    public function test_category_has_many_skills()
    {
        $category = Category::factory()->create();
        $skills = Skill::factory()->count(2)->create(['category_id' => $category->id]);

        $this->assertCount(2, $category->skills);
        $this->assertInstanceOf(Skill::class, $category->skills->first());
    }

    public function test_category_active_scope()
    {
        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);
        Category::factory()->create(['is_active' => true]);

        $activeCategories = Category::active()->get();

        $this->assertCount(2, $activeCategories);
        $this->assertTrue($activeCategories->first()->is_active);
    }

    public function test_category_inactive_scope()
    {
        Category::factory()->create(['is_active' => false]);
        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        $inactiveCategories = Category::inactive()->get();

        $this->assertCount(2, $inactiveCategories);
        $this->assertFalse($inactiveCategories->first()->is_active);
    }

    public function test_category_by_slug_scope()
    {
        $category = Category::factory()->create(['slug' => 'web-development']);
        Category::factory()->create(['slug' => 'graphic-design']);

        $foundCategory = Category::bySlug('web-development')->first();

        $this->assertNotNull($foundCategory);
        $this->assertSame('web-development', $foundCategory->slug);
    }

    public function test_category_jobs_count_accessor()
    {
        $category = Category::factory()->create();
        Job::factory()->count(5)->create(['category_id' => $category->id]);

        $this->assertSame(5, $category->jobs_count);
    }

    public function test_category_skills_count_accessor()
    {
        $category = Category::factory()->create();
        Skill::factory()->count(3)->create(['category_id' => $category->id]);

        $this->assertSame(3, $category->skills_count);
    }

    public function test_category_activate_method()
    {
        $category = Category::factory()->create(['is_active' => false]);

        $category->activate();

        $this->assertTrue($category->is_active);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => true,
        ]);
    }

    public function test_category_deactivate_method()
    {
        $category = Category::factory()->create(['is_active' => true]);

        $category->deactivate();

        $this->assertFalse($category->is_active);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_category_slug_uniqueness()
    {
        Category::factory()->create(['slug' => 'unique-slug']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Category::factory()->create(['slug' => 'unique-slug']);
    }

    public function test_category_validation_constraints()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create category without required fields
        Category::create([]);
    }

    public function test_category_soft_deletes()
    {
        $category = Category::factory()->create();
        $categoryId = $category->id;

        $category->delete();

        $this->assertSoftDeleted('categories', ['id' => $categoryId]);
        $this->assertCount(0, Category::all());
        $this->assertCount(1, Category::withTrashed()->get());
    }

    public function test_category_icon_display()
    {
        $category = Category::factory()->create(['icon' => 'fas fa-code']);

        $this->assertSame('fas fa-code', $category->icon);
    }

    public function test_category_search_functionality()
    {
        Category::factory()->create(['name' => 'Web Development', 'description' => 'Frontend and backend development']);
        Category::factory()->create(['name' => 'Graphic Design', 'description' => 'Logo and branding design']);
        Category::factory()->create(['name' => 'Mobile Development', 'description' => 'iOS and Android apps']);

        $webCategories = Category::where('name', 'LIKE', '%Web%')->get();

        $this->assertCount(1, $webCategories);
        $this->assertStringContainsString('Web', $webCategories->first()->name);
    }

    public function test_category_ordering()
    {
        Category::factory()->create(['name' => 'Zebra Category']);
        Category::factory()->create(['name' => 'Alpha Category']);
        Category::factory()->create(['name' => 'Beta Category']);

        $orderedCategories = Category::orderBy('name')->get();

        $this->assertSame('Alpha Category', $orderedCategories->first()->name);
        $this->assertSame('Zebra Category', $orderedCategories->last()->name);
    }
}
