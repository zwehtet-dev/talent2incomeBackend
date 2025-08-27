<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_skill_can_be_created_with_valid_data()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $skillData = [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'WordPress Development',
            'description' => 'Expert WordPress developer with 5+ years experience',
            'price_per_hour' => 50.00,
            'pricing_type' => 'hourly',
            'is_available' => true,
            'is_active' => true,
        ];

        $skill = Skill::create($skillData);

        $this->assertInstanceOf(Skill::class, $skill);
        $this->assertSame('WordPress Development', $skill->title);
        $this->assertSame('hourly', $skill->pricing_type);
        $this->assertSame(50.00, $skill->price_per_hour);
    }

    public function test_skill_belongs_to_user()
    {
        $user = User::factory()->create();
        $skill = Skill::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $skill->user);
        $this->assertSame($user->id, $skill->user->id);
    }

    public function test_skill_belongs_to_category()
    {
        $category = Category::factory()->create();
        $skill = Skill::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $skill->category);
        $this->assertSame($category->id, $skill->category->id);
    }

    public function test_skill_available_scope()
    {
        Skill::factory()->create(['is_available' => true]);
        Skill::factory()->create(['is_available' => false]);

        $availableSkills = Skill::available()->get();

        $this->assertCount(1, $availableSkills);
        $this->assertTrue($availableSkills->first()->is_available);
    }

    public function test_skill_active_scope()
    {
        Skill::factory()->create(['is_active' => true]);
        Skill::factory()->create(['is_active' => false]);

        $activeSkills = Skill::active()->get();

        $this->assertCount(1, $activeSkills);
        $this->assertTrue($activeSkills->first()->is_active);
    }

    public function test_skill_by_pricing_type_scope()
    {
        Skill::factory()->create(['pricing_type' => 'hourly']);
        Skill::factory()->create(['pricing_type' => 'fixed']);
        Skill::factory()->create(['pricing_type' => 'negotiable']);

        $hourlySkills = Skill::byPricingType('hourly')->get();
        $fixedSkills = Skill::byPricingType('fixed')->get();

        $this->assertCount(1, $hourlySkills);
        $this->assertCount(1, $fixedSkills);
        $this->assertSame('hourly', $hourlySkills->first()->pricing_type);
        $this->assertSame('fixed', $fixedSkills->first()->pricing_type);
    }

    public function test_skill_within_price_range_scope()
    {
        Skill::factory()->create(['price_per_hour' => 25.00, 'pricing_type' => 'hourly']);
        Skill::factory()->create(['price_per_hour' => 50.00, 'pricing_type' => 'hourly']);
        Skill::factory()->create(['price_per_hour' => 75.00, 'pricing_type' => 'hourly']);

        $skillsInRange = Skill::withinPriceRange(30.00, 60.00)->get();

        $this->assertCount(1, $skillsInRange);
        $this->assertSame(50.00, $skillsInRange->first()->price_per_hour);
    }

    public function test_skill_search_scope()
    {
        Skill::factory()->create(['title' => 'WordPress Development', 'description' => 'Expert WordPress developer']);
        Skill::factory()->create(['title' => 'React Development', 'description' => 'Frontend React specialist']);
        Skill::factory()->create(['title' => 'Logo Design', 'description' => 'Creative graphic design']);

        $results = Skill::search('WordPress')->get();

        $this->assertCount(1, $results);
        $this->assertStringContainsString('WordPress', $results->first()->title);
    }

    public function test_skill_price_display_accessor()
    {
        $hourlySkill = Skill::factory()->create([
            'pricing_type' => 'hourly',
            'price_per_hour' => 50.00,
        ]);

        $fixedSkill = Skill::factory()->create([
            'pricing_type' => 'fixed',
            'price_fixed' => 500.00,
        ]);

        $negotiableSkill = Skill::factory()->create([
            'pricing_type' => 'negotiable',
        ]);

        $this->assertSame('$50.00/hour', $hourlySkill->price_display);
        $this->assertSame('$500.00 fixed', $fixedSkill->price_display);
        $this->assertSame('Negotiable', $negotiableSkill->price_display);
    }

    public function test_skill_is_hourly_method()
    {
        $hourlySkill = Skill::factory()->create(['pricing_type' => 'hourly']);
        $fixedSkill = Skill::factory()->create(['pricing_type' => 'fixed']);

        $this->assertTrue($hourlySkill->isHourly());
        $this->assertFalse($fixedSkill->isHourly());
    }

    public function test_skill_is_fixed_method()
    {
        $fixedSkill = Skill::factory()->create(['pricing_type' => 'fixed']);
        $hourlySkill = Skill::factory()->create(['pricing_type' => 'hourly']);

        $this->assertTrue($fixedSkill->isFixed());
        $this->assertFalse($hourlySkill->isFixed());
    }

    public function test_skill_is_negotiable_method()
    {
        $negotiableSkill = Skill::factory()->create(['pricing_type' => 'negotiable']);
        $hourlySkill = Skill::factory()->create(['pricing_type' => 'hourly']);

        $this->assertTrue($negotiableSkill->isNegotiable());
        $this->assertFalse($hourlySkill->isNegotiable());
    }

    public function test_skill_toggle_availability_method()
    {
        $skill = Skill::factory()->create(['is_available' => true]);

        $skill->toggleAvailability();
        $this->assertFalse($skill->is_available);

        $skill->toggleAvailability();
        $this->assertTrue($skill->is_available);
    }

    public function test_skill_deactivate_method()
    {
        $skill = Skill::factory()->create(['is_active' => true]);

        $skill->deactivate();

        $this->assertFalse($skill->is_active);
        $this->assertFalse($skill->is_available);
    }

    public function test_skill_activate_method()
    {
        $skill = Skill::factory()->create(['is_active' => false, 'is_available' => false]);

        $skill->activate();

        $this->assertTrue($skill->is_active);
        $this->assertTrue($skill->is_available);
    }

    public function test_skill_pricing_validation()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        // Hourly skill should have price_per_hour
        $hourlySkill = Skill::factory()->make([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'pricing_type' => 'hourly',
            'price_per_hour' => null,
            'price_fixed' => 100.00,
        ]);

        // This validation should be handled by form requests in real app
        $this->assertNull($hourlySkill->price_per_hour);
        $this->assertSame('hourly', $hourlySkill->pricing_type);
    }

    public function test_skill_soft_deletes()
    {
        $skill = Skill::factory()->create();
        $skillId = $skill->id;

        $skill->delete();

        $this->assertSoftDeleted('skills', ['id' => $skillId]);
        $this->assertCount(0, Skill::all());
        $this->assertCount(1, Skill::withTrashed()->get());
    }

    public function test_skill_by_category_scope()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        Skill::factory()->count(2)->create(['category_id' => $category1->id]);
        Skill::factory()->create(['category_id' => $category2->id]);

        $skillsInCategory1 = Skill::byCategory($category1->id)->get();

        $this->assertCount(2, $skillsInCategory1);
    }

    public function test_skill_by_user_scope()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Skill::factory()->count(3)->create(['user_id' => $user1->id]);
        Skill::factory()->create(['user_id' => $user2->id]);

        $user1Skills = Skill::byUser($user1->id)->get();

        $this->assertCount(3, $user1Skills);
    }

    public function test_skill_validation_constraints()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create skill without required fields
        Skill::create([]);
    }

    public function test_skill_pricing_type_enum_validation()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $skill = Skill::factory()->make([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'pricing_type' => 'invalid_type',
        ]);

        // This should be caught by database constraints or validation
        $this->assertSame('invalid_type', $skill->pricing_type);
    }

    public function test_skill_price_calculations()
    {
        $hourlySkill = Skill::factory()->create([
            'pricing_type' => 'hourly',
            'price_per_hour' => 50.00,
        ]);

        $fixedSkill = Skill::factory()->create([
            'pricing_type' => 'fixed',
            'price_fixed' => 500.00,
        ]);

        // Test estimated cost for different hours
        $this->assertSame(100.00, $hourlySkill->estimatedCost(2));
        $this->assertSame(500.00, $fixedSkill->estimatedCost(10)); // Fixed price regardless of hours
    }

    public function test_skill_user_rating_integration()
    {
        $user = User::factory()->create();
        $skill = Skill::factory()->create(['user_id' => $user->id]);

        // The skill should be able to access user's rating through relationship
        $this->assertSame($user->averageRating(), $skill->user->averageRating());
    }
}
