<?php

namespace Tests\Unit\Models;

use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_valid_data()
    {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password123', // Plain password, will be hashed by model
            'bio' => 'Experienced developer',
            'location' => 'New York, NY',
            'phone' => '+1234567890',
        ];

        $user = User::create($userData);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('John', $user->first_name);
        $this->assertSame('john@example.com', $user->email);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_user_full_name_accessor()
    {
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertSame('John Doe', $user->full_name);
    }

    public function test_user_has_many_jobs()
    {
        $user = User::factory()->create();
        $jobs = Job::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->jobs);
        $this->assertInstanceOf(Job::class, $user->jobs->first());
    }

    public function test_user_has_many_skills()
    {
        $user = User::factory()->create();
        $skills = Skill::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->skills);
        $this->assertInstanceOf(Skill::class, $user->skills->first());
    }

    public function test_user_has_many_sent_messages()
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();
        $messages = Message::factory()->count(3)->create([
            'sender_id' => $user->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->assertCount(3, $user->sentMessages);
        $this->assertInstanceOf(Message::class, $user->sentMessages->first());
    }

    public function test_user_has_many_received_messages()
    {
        $sender = User::factory()->create();
        $user = User::factory()->create();
        $messages = Message::factory()->count(2)->create([
            'sender_id' => $sender->id,
            'recipient_id' => $user->id,
        ]);

        $this->assertCount(2, $user->receivedMessages);
        $this->assertInstanceOf(Message::class, $user->receivedMessages->first());
    }

    public function test_user_has_many_reviews_given()
    {
        $user = User::factory()->create();
        $reviewee = User::factory()->create();
        $job = Job::factory()->create();

        $reviews = Review::factory()->count(2)->create([
            'reviewer_id' => $user->id,
            'reviewee_id' => $reviewee->id,
            'job_id' => $job->id,
        ]);

        $this->assertCount(2, $user->reviewsGiven);
        $this->assertInstanceOf(Review::class, $user->reviewsGiven->first());
    }

    public function test_user_has_many_reviews_received()
    {
        $reviewer = User::factory()->create();
        $user = User::factory()->create();
        $job = Job::factory()->create();

        $reviews = Review::factory()->count(3)->create([
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $user->id,
            'job_id' => $job->id,
        ]);

        $this->assertCount(3, $user->reviewsReceived);
        $this->assertInstanceOf(Review::class, $user->reviewsReceived->first());
    }

    public function test_user_average_rating_calculation()
    {
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        $job = Job::factory()->create();

        // Create reviews with ratings 4, 5, 3
        Review::factory()->create([
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $user->id,
            'job_id' => $job->id,
            'rating' => 4,
        ]);
        Review::factory()->create([
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $user->id,
            'job_id' => Job::factory()->create()->id,
            'rating' => 5,
        ]);
        Review::factory()->create([
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $user->id,
            'job_id' => Job::factory()->create()->id,
            'rating' => 3,
        ]);

        $this->assertSame(4.0, $user->averageRating());
    }

    public function test_user_average_rating_returns_zero_with_no_reviews()
    {
        $user = User::factory()->create();

        $this->assertSame(0, $user->averageRating());
    }

    public function test_user_can_block_another_user()
    {
        $user = User::factory()->create();
        $blockedUser = User::factory()->create();

        $user->blockUser($blockedUser->id);

        $this->assertTrue($user->hasBlocked($blockedUser->id));
        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $user->id,
            'blocked_id' => $blockedUser->id,
        ]);
    }

    public function test_user_can_unblock_another_user()
    {
        $user = User::factory()->create();
        $blockedUser = User::factory()->create();

        $user->blockUser($blockedUser->id);
        $this->assertTrue($user->hasBlocked($blockedUser->id));

        $user->unblockUser($blockedUser->id);
        $this->assertFalse($user->hasBlocked($blockedUser->id));
    }

    public function test_user_cannot_block_themselves()
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $user->blockUser($user->id);
    }

    public function test_user_is_active_scope()
    {
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => false]);

        $activeUsers = User::active()->get();

        $this->assertCount(1, $activeUsers);
        $this->assertTrue($activeUsers->first()->is_active);
    }

    public function test_user_email_verification()
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->assertFalse($user->hasVerifiedEmail());

        $user->markEmailAsVerified();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_user_password_hashing()
    {
        $user = User::factory()->create();
        $plainPassword = 'newpassword123';

        $user->password = $plainPassword;
        $user->save();

        $this->assertTrue(Hash::check($plainPassword, $user->password));
    }

    public function test_user_soft_deletes()
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $userId]);
        $this->assertCount(0, User::all());
        $this->assertCount(1, User::withTrashed()->get());
    }

    public function test_user_jobs_completed_count()
    {
        $user = User::factory()->create();

        // Create completed jobs
        Job::factory()->count(3)->create([
            'assigned_to' => $user->id,
            'status' => 'completed',
        ]);

        // Create non-completed jobs
        Job::factory()->count(2)->create([
            'assigned_to' => $user->id,
            'status' => 'in_progress',
        ]);

        $this->assertSame(3, $user->jobsCompletedCount());
    }

    public function test_user_total_earnings()
    {
        $user = User::factory()->create();

        // Create released payments
        Payment::factory()->count(2)->create([
            'payee_id' => $user->id,
            'amount' => 100.00,
            'status' => 'released',
        ]);

        // Create non-released payment
        Payment::factory()->create([
            'payee_id' => $user->id,
            'amount' => 50.00,
            'status' => 'held',
        ]);

        $this->assertSame(200.00, $user->totalEarnings());
    }

    public function test_user_validation_rules()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create user without required fields
        User::create([]);
    }

    public function test_user_email_uniqueness()
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['email' => 'test@example.com']);
    }
}
