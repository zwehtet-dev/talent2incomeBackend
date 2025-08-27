<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'is_admin' => false,
        ]);

        $this->admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'is_admin' => true,
        ]);

        $this->otherUser = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'is_admin' => false,
        ]);
    }

    /** @test */
    public function user_can_authorize_profile_operations()
    {
        Sanctum::actingAs($this->user);

        // User can view their own profile
        $this->assertTrue($this->user->can('view', $this->user));

        // User cannot view other profiles (unless admin)
        $this->assertFalse($this->user->can('view', $this->otherUser));

        // User can update their own profile
        $this->assertTrue($this->user->can('update', $this->user));

        // User cannot update other profiles
        $this->assertFalse($this->user->can('update', $this->otherUser));
    }

    /** @test */
    public function admin_can_authorize_all_user_operations()
    {
        Sanctum::actingAs($this->admin);

        // Admin can view any profile
        $this->assertTrue($this->admin->can('view', $this->user));
        $this->assertTrue($this->admin->can('view', $this->otherUser));

        // Admin can delete any user
        $this->assertTrue($this->admin->can('delete', $this->user));

        // Admin can access admin gates
        $this->assertTrue($this->admin->can('admin.dashboard'));
        $this->assertTrue($this->admin->can('admin.users.manage'));
    }

    /** @test */
    public function job_authorization_works_with_ownership()
    {
        $job = Job::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);

        // Owner can update their job
        $this->assertTrue($this->user->can('update', $job));

        // Owner can delete their job (if no one assigned)
        $this->assertTrue($this->user->can('delete', $job));

        Sanctum::actingAs($this->otherUser);

        // Other users cannot update the job
        $this->assertFalse($this->otherUser->can('update', $job));

        // Other users can apply to the job
        $this->assertTrue($this->otherUser->can('apply', $job));
    }

    /** @test */
    public function skill_authorization_works_with_ownership()
    {
        $skill = Skill::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);

        // Owner can update their skill
        $this->assertTrue($this->user->can('update', $skill));
        $this->assertTrue($this->user->can('updateAvailability', $skill));

        Sanctum::actingAs($this->otherUser);

        // Other users cannot update the skill
        $this->assertFalse($this->otherUser->can('update', $skill));

        // Other users can contact skill provider
        $this->assertTrue($this->otherUser->can('contact', $skill));
    }

    /** @test */
    public function message_authorization_works_with_participants()
    {
        $message = Message::factory()->create([
            'sender_id' => $this->user->id,
            'recipient_id' => $this->otherUser->id,
        ]);

        Sanctum::actingAs($this->user);

        // Sender can view the message
        $this->assertTrue($this->user->can('view', $message));

        // Sender can delete within 24 hours
        $this->assertTrue($this->user->can('delete', $message));

        Sanctum::actingAs($this->otherUser);

        // Recipient can view the message
        $this->assertTrue($this->otherUser->can('view', $message));

        // Recipient can mark as read
        $this->assertTrue($this->otherUser->can('markAsRead', $message));

        // Recipient cannot delete sender's message
        $this->assertFalse($this->otherUser->can('delete', $message));
    }

    /** @test */
    public function payment_authorization_works_with_transaction_parties()
    {
        $job = Job::factory()->create([
            'user_id' => $this->user->id,
            'assigned_to' => $this->otherUser->id,
            'status' => 'completed',
        ]);

        $payment = Payment::factory()->create([
            'job_id' => $job->id,
            'payer_id' => $this->user->id,
            'payee_id' => $this->otherUser->id,
            'status' => 'held',
        ]);

        Sanctum::actingAs($this->user);

        // Payer can view and release payment
        $this->assertTrue($this->user->can('view', $payment));
        $this->assertTrue($this->user->can('release', $payment));
        $this->assertTrue($this->user->can('requestRefund', $payment));

        Sanctum::actingAs($this->otherUser);

        // Payee can view but not release payment
        $this->assertTrue($this->otherUser->can('view', $payment));
        $this->assertFalse($this->otherUser->can('release', $payment));

        // Both parties can dispute
        $this->assertTrue($this->otherUser->can('dispute', $payment));
    }

    /** @test */
    public function review_authorization_works_with_job_completion()
    {
        $job = Job::factory()->create([
            'user_id' => $this->user->id,
            'assigned_to' => $this->otherUser->id,
            'status' => 'completed',
        ]);

        Sanctum::actingAs($this->user);

        // Job owner can create reviews
        $this->assertTrue($this->user->can('create', Review::class));

        // Test the policy methods directly
        $reviewPolicy = new \App\Policies\ReviewPolicy();
        $this->assertTrue($reviewPolicy->createForJob($this->user, $job));
        $this->assertTrue($reviewPolicy->reviewUser($this->user, $this->otherUser, $job));

        Sanctum::actingAs($this->otherUser);

        // Worker can create reviews
        $this->assertTrue($this->otherUser->can('create', Review::class));

        // Test the policy methods directly
        $this->assertTrue($reviewPolicy->createForJob($this->otherUser, $job));
        $this->assertTrue($reviewPolicy->reviewUser($this->otherUser, $this->user, $job));

        // Test with incomplete job
        $incompleteJob = Job::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        $this->assertFalse($reviewPolicy->createForJob($this->user, $incompleteJob));
    }

    /** @test */
    public function inactive_users_are_properly_restricted()
    {
        $inactiveUser = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($inactiveUser);

        // Inactive users cannot create content
        $this->assertFalse($inactiveUser->can('create', Job::class));
        $this->assertFalse($inactiveUser->can('create', Skill::class));
        $this->assertFalse($inactiveUser->can('create', Message::class));
    }

    /** @test */
    public function unverified_users_are_properly_restricted()
    {
        $unverifiedUser = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($unverifiedUser);

        // Unverified users cannot create content
        $this->assertFalse($unverifiedUser->can('create', Job::class));
        $this->assertFalse($unverifiedUser->can('create', Skill::class));
        $this->assertFalse($unverifiedUser->can('create', Message::class));
    }

    /** @test */
    public function admin_gates_work_correctly()
    {
        Sanctum::actingAs($this->admin);

        // Admin can access all admin functions
        $this->assertTrue($this->admin->can('admin.dashboard'));
        $this->assertTrue($this->admin->can('admin.users.manage'));
        $this->assertTrue($this->admin->can('admin.payments.manage'));
        $this->assertTrue($this->admin->can('admin.disputes.handle'));
        $this->assertTrue($this->admin->can('admin.content.moderate'));

        Sanctum::actingAs($this->user);

        // Regular users cannot access admin functions
        $this->assertFalse($this->user->can('admin.dashboard'));
        $this->assertFalse($this->user->can('admin.users.manage'));
        $this->assertFalse($this->user->can('admin.payments.manage'));
    }
}
