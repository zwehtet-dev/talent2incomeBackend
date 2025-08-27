<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_creates_valid_user(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->first_name);
        $this->assertNotNull($user->last_name);
        $this->assertNotNull($user->email);
        $this->assertTrue(is_bool($user->is_active));
        $this->assertTrue(is_bool($user->is_admin));
    }

    public function test_category_factory_creates_valid_category(): void
    {
        $category = Category::factory()->create();

        $this->assertNotNull($category->name);
        $this->assertNotNull($category->slug);
        $this->assertTrue(is_bool($category->is_active));
        $this->assertIsInt($category->lft);
        $this->assertIsInt($category->rgt);
        $this->assertIsInt($category->depth);
    }

    public function test_skill_factory_creates_valid_skill(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $skill = Skill::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertNotNull($skill->title);
        $this->assertNotNull($skill->description);
        $this->assertNotNull($skill->pricing_type);
        $this->assertTrue(in_array($skill->pricing_type, ['hourly', 'fixed', 'negotiable']));
        $this->assertTrue(is_bool($skill->is_available));
        $this->assertTrue(is_bool($skill->is_active));
    }

    public function test_job_factory_creates_valid_job(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $job = Job::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $this->assertNotNull($job->title);
        $this->assertNotNull($job->description);
        $this->assertNotNull($job->budget_type);
        $this->assertTrue(in_array($job->budget_type, ['hourly', 'fixed', 'negotiable']));
        $this->assertNotNull($job->status);
        $this->assertTrue(is_bool($job->is_urgent));
    }

    public function test_message_factory_creates_valid_message(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $message = Message::factory()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->assertNotNull($message->content);
        $this->assertTrue(is_bool($message->is_read));
        $this->assertSame($sender->id, $message->sender_id);
        $this->assertSame($recipient->id, $message->recipient_id);
    }

    public function test_payment_factory_creates_valid_payment(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $job = Job::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payment = Payment::factory()->create([
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
        ]);

        $this->assertNotNull($payment->amount);
        $this->assertNotNull($payment->platform_fee);
        $this->assertNotNull($payment->status);
        $this->assertNotNull($payment->payment_method);
        $this->assertTrue(in_array($payment->status, ['pending', 'held', 'released', 'refunded', 'failed', 'disputed']));
    }

    public function test_review_factory_creates_valid_review(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $job = Job::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        $reviewer = User::factory()->create();
        $reviewee = User::factory()->create();

        $review = Review::factory()->create([
            'job_id' => $job->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $reviewee->id,
        ]);

        $this->assertNotNull($review->rating);
        $this->assertTrue($review->rating >= 1 && $review->rating <= 5);
        $this->assertTrue(is_bool($review->is_public));
        $this->assertTrue(is_bool($review->is_flagged));
    }

    public function test_factory_states_work_correctly(): void
    {
        // Test User factory states
        $adminUser = User::factory()->admin()->create();
        $this->assertTrue($adminUser->is_admin);

        $inactiveUser = User::factory()->inactive()->create();
        $this->assertFalse($inactiveUser->is_active);

        // Test Skill factory states
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $hourlySkill = Skill::factory()->hourly()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        $this->assertSame('hourly', $hourlySkill->pricing_type);
        $this->assertNotNull($hourlySkill->price_per_hour);

        $fixedSkill = Skill::factory()->fixed()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        $this->assertSame('fixed', $fixedSkill->pricing_type);
        $this->assertNotNull($fixedSkill->price_fixed);

        // Test Job factory states
        $openJob = Job::factory()->open()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        $this->assertSame('open', $openJob->status);

        $urgentJob = Job::factory()->urgent()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);
        $this->assertTrue($urgentJob->is_urgent);

        // Test Review factory states
        $fiveStarReview = Review::factory()->fiveStars()->create([
            'job_id' => $openJob->id,
            'reviewer_id' => $user->id,
            'reviewee_id' => User::factory()->create()->id,
        ]);
        $this->assertSame(5, $fiveStarReview->rating);
    }
}
