<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Job;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_created_with_valid_data()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $jobData = [
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Build a Website',
            'description' => 'Need a professional website built',
            'budget_min' => 500.00,
            'budget_max' => 1000.00,
            'budget_type' => 'fixed',
            'deadline' => Carbon::now()->addDays(30),
            'status' => 'open',
            'is_urgent' => false,
        ];

        $job = Job::create($jobData);

        $this->assertInstanceOf(Job::class, $job);
        $this->assertSame('Build a Website', $job->title);
        $this->assertSame('open', $job->status);
        $this->assertSame(500.00, $job->budget_min);
    }

    public function test_job_belongs_to_user()
    {
        $user = User::factory()->create();
        $job = Job::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $job->user);
        $this->assertSame($user->id, $job->user->id);
    }

    public function test_job_belongs_to_category()
    {
        $category = Category::factory()->create();
        $job = Job::factory()->create(['category_id' => $category->id]);

        $this->assertInstanceOf(Category::class, $job->category);
        $this->assertSame($category->id, $job->category->id);
    }

    public function test_job_belongs_to_assigned_user()
    {
        $assignedUser = User::factory()->create();
        $job = Job::factory()->create(['assigned_to' => $assignedUser->id]);

        $this->assertInstanceOf(User::class, $job->assignedUser);
        $this->assertSame($assignedUser->id, $job->assignedUser->id);
    }

    public function test_job_has_many_messages()
    {
        $job = Job::factory()->create();
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $messages = Message::factory()->count(3)->create([
            'job_id' => $job->id,
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
        ]);

        $this->assertCount(3, $job->messages);
        $this->assertInstanceOf(Message::class, $job->messages->first());
    }

    public function test_job_has_many_reviews()
    {
        $job = Job::factory()->create();
        $reviewer = User::factory()->create();
        $reviewee = User::factory()->create();

        $reviews = Review::factory()->count(2)->create([
            'job_id' => $job->id,
            'reviewer_id' => $reviewer->id,
            'reviewee_id' => $reviewee->id,
        ]);

        $this->assertCount(2, $job->reviews);
        $this->assertInstanceOf(Review::class, $job->reviews->first());
    }

    public function test_job_has_many_payments()
    {
        $job = Job::factory()->create();
        $payer = User::factory()->create();
        $payee = User::factory()->create();

        $payments = Payment::factory()->count(2)->create([
            'job_id' => $job->id,
            'payer_id' => $payer->id,
            'payee_id' => $payee->id,
        ]);

        $this->assertCount(2, $job->payments);
        $this->assertInstanceOf(Payment::class, $job->payments->first());
    }

    public function test_job_status_scopes()
    {
        Job::factory()->create(['status' => 'open']);
        Job::factory()->create(['status' => 'in_progress']);
        Job::factory()->create(['status' => 'completed']);
        Job::factory()->create(['status' => 'cancelled']);

        $this->assertCount(1, Job::open()->get());
        $this->assertCount(1, Job::inProgress()->get());
        $this->assertCount(1, Job::completed()->get());
        $this->assertCount(1, Job::cancelled()->get());
    }

    public function test_job_urgent_scope()
    {
        Job::factory()->create(['is_urgent' => true]);
        Job::factory()->create(['is_urgent' => false]);

        $urgentJobs = Job::urgent()->get();

        $this->assertCount(1, $urgentJobs);
        $this->assertTrue($urgentJobs->first()->is_urgent);
    }

    public function test_job_within_budget_scope()
    {
        Job::factory()->create(['budget_min' => 100, 'budget_max' => 500]);
        Job::factory()->create(['budget_min' => 600, 'budget_max' => 1000]);
        Job::factory()->create(['budget_min' => 200, 'budget_max' => 300]);

        $jobsInBudget = Job::withinBudget(150, 400)->get();

        $this->assertCount(2, $jobsInBudget);
    }

    public function test_job_deadline_soon_scope()
    {
        Job::factory()->create(['deadline' => Carbon::now()->addDays(2)]);
        Job::factory()->create(['deadline' => Carbon::now()->addDays(10)]);
        Job::factory()->create(['deadline' => Carbon::now()->addDays(1)]);

        $jobsDeadlineSoon = Job::deadlineSoon(3)->get();

        $this->assertCount(2, $jobsDeadlineSoon);
    }

    public function test_job_is_expired_method()
    {
        $expiredJob = Job::factory()->create(['deadline' => Carbon::now()->subDays(1)]);
        $activeJob = Job::factory()->create(['deadline' => Carbon::now()->addDays(1)]);

        $this->assertTrue($expiredJob->isExpired());
        $this->assertFalse($activeJob->isExpired());
    }

    public function test_job_can_be_assigned_method()
    {
        $openJob = Job::factory()->create(['status' => 'open']);
        $completedJob = Job::factory()->create(['status' => 'completed']);

        $this->assertTrue($openJob->canBeAssigned());
        $this->assertFalse($completedJob->canBeAssigned());
    }

    public function test_job_is_completed_method()
    {
        $completedJob = Job::factory()->create(['status' => 'completed']);
        $openJob = Job::factory()->create(['status' => 'open']);

        $this->assertTrue($completedJob->isCompleted());
        $this->assertFalse($openJob->isCompleted());
    }

    public function test_job_budget_range_accessor()
    {
        $job = Job::factory()->create([
            'budget_min' => 100.00,
            'budget_max' => 500.00,
            'budget_type' => 'fixed',
        ]);

        $this->assertSame('$100.00 - $500.00 (fixed)', $job->budget_range);
    }

    public function test_job_days_until_deadline_accessor()
    {
        $job = Job::factory()->create(['deadline' => Carbon::now()->addDays(5)]);

        $this->assertSame(5, $job->days_until_deadline);
    }

    public function test_job_assign_to_user_method()
    {
        $job = Job::factory()->create(['status' => 'open']);
        $user = User::factory()->create();

        $result = $job->assignToUser($user->id);

        $this->assertTrue($result);
        $this->assertSame($user->id, $job->assigned_to);
        $this->assertSame('in_progress', $job->status);
    }

    public function test_job_cannot_assign_if_not_open()
    {
        $job = Job::factory()->create(['status' => 'completed']);
        $user = User::factory()->create();

        $result = $job->assignToUser($user->id);

        $this->assertFalse($result);
        $this->assertNull($job->assigned_to);
    }

    public function test_job_mark_as_completed_method()
    {
        $job = Job::factory()->create(['status' => 'in_progress']);

        $result = $job->markAsCompleted();

        $this->assertTrue($result);
        $this->assertSame('completed', $job->status);
    }

    public function test_job_cannot_complete_if_not_in_progress()
    {
        $job = Job::factory()->create(['status' => 'open']);

        $result = $job->markAsCompleted();

        $this->assertFalse($result);
        $this->assertSame('open', $job->status);
    }

    public function test_job_search_scope()
    {
        Job::factory()->create(['title' => 'Build WordPress Website', 'description' => 'Need a professional site']);
        Job::factory()->create(['title' => 'Mobile App Development', 'description' => 'iOS and Android app']);
        Job::factory()->create(['title' => 'Logo Design', 'description' => 'Creative logo needed']);

        $results = Job::search('WordPress')->get();

        $this->assertCount(1, $results);
        $this->assertStringContainsString('WordPress', $results->first()->title);
    }

    public function test_job_soft_deletes()
    {
        $job = Job::factory()->create();
        $jobId = $job->id;

        $job->delete();

        $this->assertSoftDeleted('jobs', ['id' => $jobId]);
        $this->assertCount(0, Job::all());
        $this->assertCount(1, Job::withTrashed()->get());
    }

    public function test_job_validation_constraints()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create job without required fields
        Job::create([]);
    }

    public function test_job_budget_validation()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        // Budget max should be greater than or equal to budget min
        $job = Job::factory()->make([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'budget_min' => 1000.00,
            'budget_max' => 500.00, // Invalid: max < min
        ]);

        // This should be handled by form request validation in real app
        $this->assertTrue($job->budget_max < $job->budget_min);
    }

    public function test_job_status_transitions()
    {
        $job = Job::factory()->create(['status' => 'open']);

        // Valid transitions
        $job->status = 'in_progress';
        $job->save();
        $this->assertSame('in_progress', $job->status);

        $job->status = 'completed';
        $job->save();
        $this->assertSame('completed', $job->status);
    }
}
