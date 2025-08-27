<?php

namespace Tests\Unit;

use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Skill;
use App\Models\User;
use App\Policies\AdminPolicy;
use App\Policies\JobPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReviewPolicy;
use App\Policies\SkillPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
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
    public function user_policy_allows_users_to_view_own_profile()
    {
        $policy = new UserPolicy();

        $this->assertTrue($policy->view($this->user, $this->user));
        $this->assertFalse($policy->view($this->user, $this->otherUser));
        $this->assertTrue($policy->view($this->admin, $this->user));
    }

    /** @test */
    public function user_policy_allows_users_to_update_own_profile()
    {
        $policy = new UserPolicy();

        $this->assertTrue($policy->update($this->user, $this->user));
        $this->assertFalse($policy->update($this->user, $this->otherUser));
    }

    /** @test */
    public function job_policy_allows_verified_users_to_create_jobs()
    {
        $policy = new JobPolicy();

        $this->assertTrue($policy->create($this->user));

        $unverifiedUser = User::factory()->create([
            'email_verified_at' => null,
            'is_active' => true,
        ]);
        $this->assertFalse($policy->create($unverifiedUser));
    }

    /** @test */
    public function job_policy_allows_owner_to_update_open_jobs()
    {
        $policy = new JobPolicy();
        $job = Job::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);

        $this->assertTrue($policy->update($this->user, $job));
        $this->assertFalse($policy->update($this->otherUser, $job));

        $job->status = 'completed';
        $job->save();
        $this->assertFalse($policy->update($this->user, $job));
    }

    /** @test */
    public function skill_policy_allows_owner_to_manage_skills()
    {
        $policy = new SkillPolicy();
        $skill = Skill::factory()->create(['user_id' => $this->user->id]);

        $this->assertTrue($policy->update($this->user, $skill));
        $this->assertTrue($policy->delete($this->user, $skill));
        $this->assertFalse($policy->update($this->otherUser, $skill));
        $this->assertTrue($policy->delete($this->admin, $skill)); // Admin can delete
    }

    /** @test */
    public function message_policy_allows_participants_to_view_messages()
    {
        $policy = new MessagePolicy();
        $message = Message::factory()->create([
            'sender_id' => $this->user->id,
            'recipient_id' => $this->otherUser->id,
        ]);

        $this->assertTrue($policy->view($this->user, $message));
        $this->assertTrue($policy->view($this->otherUser, $message));
        $this->assertTrue($policy->view($this->admin, $message));

        $thirdUser = User::factory()->create();
        $this->assertFalse($policy->view($thirdUser, $message));
    }

    /** @test */
    public function payment_policy_restricts_payment_operations()
    {
        $policy = new PaymentPolicy();
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

        $this->assertTrue($policy->view($this->user, $payment));
        $this->assertTrue($policy->view($this->otherUser, $payment));
        $this->assertTrue($policy->release($this->user, $payment));
        $this->assertFalse($policy->release($this->otherUser, $payment));
    }

    /** @test */
    public function review_policy_allows_job_participants_to_review()
    {
        $policy = new ReviewPolicy();
        $job = Job::factory()->create([
            'user_id' => $this->user->id,
            'assigned_to' => $this->otherUser->id,
            'status' => 'completed',
        ]);

        $this->assertTrue($policy->createForJob($this->user, $job));
        $this->assertTrue($policy->createForJob($this->otherUser, $job));

        $thirdUser = User::factory()->create();
        $this->assertFalse($policy->createForJob($thirdUser, $job));

        // Test incomplete job
        $incompleteJob = Job::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);
        $this->assertFalse($policy->createForJob($this->user, $incompleteJob));
    }

    /** @test */
    public function admin_policy_restricts_access_to_admins_only()
    {
        $policy = new AdminPolicy();

        $this->assertTrue($policy->viewDashboard($this->admin));
        $this->assertTrue($policy->manageUsers($this->admin));
        $this->assertTrue($policy->handleDisputes($this->admin));

        $this->assertFalse($policy->viewDashboard($this->user));
        $this->assertFalse($policy->manageUsers($this->user));
        $this->assertFalse($policy->handleDisputes($this->user));
    }

    /** @test */
    public function policies_handle_inactive_users_correctly()
    {
        $inactiveUser = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $jobPolicy = new JobPolicy();
        $skillPolicy = new SkillPolicy();
        $messagePolicy = new MessagePolicy();

        $this->assertFalse($jobPolicy->create($inactiveUser));
        $this->assertFalse($skillPolicy->create($inactiveUser));
        $this->assertFalse($messagePolicy->create($inactiveUser));
    }

    /** @test */
    public function policies_handle_unverified_users_correctly()
    {
        $unverifiedUser = User::factory()->create([
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $jobPolicy = new JobPolicy();
        $skillPolicy = new SkillPolicy();
        $messagePolicy = new MessagePolicy();

        $this->assertFalse($jobPolicy->create($unverifiedUser));
        $this->assertFalse($skillPolicy->create($unverifiedUser));
        $this->assertFalse($messagePolicy->create($unverifiedUser));
    }
}
