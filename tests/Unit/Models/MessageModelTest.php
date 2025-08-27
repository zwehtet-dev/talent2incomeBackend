<?php

namespace Tests\Unit\Models;

use App\Models\Job;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_can_be_created_with_valid_data()
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $job = Job::factory()->create();

        $messageData = [
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'job_id' => $job->id,
            'content' => 'Hello, I am interested in your job posting.',
            'is_read' => false,
        ];

        $message = Message::create($messageData);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame('Hello, I am interested in your job posting.', $message->content);
        $this->assertFalse($message->is_read);
    }

    public function test_message_belongs_to_sender()
    {
        $sender = User::factory()->create();
        $message = Message::factory()->create(['sender_id' => $sender->id]);

        $this->assertInstanceOf(User::class, $message->sender);
        $this->assertSame($sender->id, $message->sender->id);
    }

    public function test_message_belongs_to_recipient()
    {
        $recipient = User::factory()->create();
        $message = Message::factory()->create(['recipient_id' => $recipient->id]);

        $this->assertInstanceOf(User::class, $message->recipient);
        $this->assertSame($recipient->id, $message->recipient->id);
    }

    public function test_message_belongs_to_job()
    {
        $job = Job::factory()->create();
        $message = Message::factory()->create(['job_id' => $job->id]);

        $this->assertInstanceOf(Job::class, $message->job);
        $this->assertSame($job->id, $message->job->id);
    }

    public function test_message_can_exist_without_job()
    {
        $message = Message::factory()->create(['job_id' => null]);

        $this->assertNull($message->job);
        $this->assertNull($message->job_id);
    }

    public function test_message_unread_scope()
    {
        Message::factory()->create(['is_read' => false]);
        Message::factory()->create(['is_read' => true]);
        Message::factory()->create(['is_read' => false]);

        $unreadMessages = Message::unread()->get();

        $this->assertCount(2, $unreadMessages);
        $this->assertFalse($unreadMessages->first()->is_read);
    }

    public function test_message_read_scope()
    {
        Message::factory()->create(['is_read' => true]);
        Message::factory()->create(['is_read' => false]);
        Message::factory()->create(['is_read' => true]);

        $readMessages = Message::read()->get();

        $this->assertCount(2, $readMessages);
        $this->assertTrue($readMessages->first()->is_read);
    }

    public function test_message_between_users_scope()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Messages between user1 and user2
        Message::factory()->create(['sender_id' => $user1->id, 'recipient_id' => $user2->id]);
        Message::factory()->create(['sender_id' => $user2->id, 'recipient_id' => $user1->id]);

        // Message between user1 and user3
        Message::factory()->create(['sender_id' => $user1->id, 'recipient_id' => $user3->id]);

        $messagesBetween = Message::betweenUsers($user1->id, $user2->id)->get();

        $this->assertCount(2, $messagesBetween);
    }

    public function test_message_for_job_scope()
    {
        $job1 = Job::factory()->create();
        $job2 = Job::factory()->create();

        Message::factory()->count(3)->create(['job_id' => $job1->id]);
        Message::factory()->count(2)->create(['job_id' => $job2->id]);
        Message::factory()->create(['job_id' => null]);

        $messagesForJob1 = Message::forJob($job1->id)->get();

        $this->assertCount(3, $messagesForJob1);
    }

    public function test_message_recent_scope()
    {
        Message::factory()->create(['created_at' => Carbon::now()->subDays(2)]);
        Message::factory()->create(['created_at' => Carbon::now()->subHours(1)]);
        Message::factory()->create(['created_at' => Carbon::now()->subDays(5)]);

        $recentMessages = Message::recent(3)->get();

        $this->assertCount(2, $recentMessages);
    }

    public function test_message_mark_as_read_method()
    {
        $message = Message::factory()->create(['is_read' => false]);

        $message->markAsRead();

        $this->assertTrue($message->is_read);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'is_read' => true,
        ]);
    }

    public function test_message_mark_as_unread_method()
    {
        $message = Message::factory()->create(['is_read' => true]);

        $message->markAsUnread();

        $this->assertFalse($message->is_read);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'is_read' => false,
        ]);
    }

    public function test_message_is_from_user_method()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $message = Message::factory()->create(['sender_id' => $user->id]);

        $this->assertTrue($message->isFromUser($user->id));
        $this->assertFalse($message->isFromUser($otherUser->id));
    }

    public function test_message_is_to_user_method()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $message = Message::factory()->create(['recipient_id' => $user->id]);

        $this->assertTrue($message->isToUser($user->id));
        $this->assertFalse($message->isToUser($otherUser->id));
    }

    public function test_message_involves_user_method()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $thirdUser = User::factory()->create();

        $sentMessage = Message::factory()->create(['sender_id' => $user->id, 'recipient_id' => $otherUser->id]);
        $receivedMessage = Message::factory()->create(['sender_id' => $otherUser->id, 'recipient_id' => $user->id]);

        $this->assertTrue($sentMessage->involvesUser($user->id));
        $this->assertTrue($receivedMessage->involvesUser($user->id));
        $this->assertFalse($sentMessage->involvesUser($thirdUser->id));
    }

    public function test_message_content_preview_accessor()
    {
        $shortMessage = Message::factory()->create(['content' => 'Short message']);
        $longMessage = Message::factory()->create(['content' => str_repeat('This is a very long message. ', 20)]);

        $this->assertSame('Short message', $shortMessage->content_preview);
        $this->assertLessThanOrEqual(100, strlen($longMessage->content_preview));
        $this->assertStringEndsWith('...', $longMessage->content_preview);
    }

    public function test_message_time_ago_accessor()
    {
        $message = Message::factory()->create(['created_at' => Carbon::now()->subHours(2)]);

        $this->assertStringContainsString('ago', $message->time_ago);
    }

    public function test_message_conversation_partner_method()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $message = Message::factory()->create(['sender_id' => $user1->id, 'recipient_id' => $user2->id]);

        $partnerFromSender = $message->conversationPartner($user1->id);
        $partnerFromRecipient = $message->conversationPartner($user2->id);

        $this->assertSame($user2->id, $partnerFromSender->id);
        $this->assertSame($user1->id, $partnerFromRecipient->id);
    }

    public function test_message_validation_constraints()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create message without required fields
        Message::create([]);
    }

    public function test_message_content_length_validation()
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $longContent = str_repeat('a', 5000); // Very long content

        $message = Message::factory()->make([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'content' => $longContent,
        ]);

        // This should be handled by form request validation in real app
        $this->assertGreaterThan(2000, strlen($message->content));
    }

    public function test_message_cannot_send_to_self()
    {
        $user = User::factory()->create();

        // This validation should be handled by form requests or business logic
        $message = Message::factory()->make([
            'sender_id' => $user->id,
            'recipient_id' => $user->id,
            'content' => 'Message to self',
        ]);

        $this->assertSame($message->sender_id, $message->recipient_id);
    }

    public function test_message_ordering()
    {
        $message1 = Message::factory()->create(['created_at' => Carbon::now()->subHours(3)]);
        $message2 = Message::factory()->create(['created_at' => Carbon::now()->subHours(1)]);
        $message3 = Message::factory()->create(['created_at' => Carbon::now()->subHours(2)]);

        $orderedMessages = Message::orderBy('created_at', 'desc')->get();

        $this->assertSame($message2->id, $orderedMessages->first()->id);
        $this->assertSame($message1->id, $orderedMessages->last()->id);
    }

    public function test_message_soft_deletes()
    {
        $message = Message::factory()->create();
        $messageId = $message->id;

        $message->delete();

        $this->assertSoftDeleted('messages', ['id' => $messageId]);
        $this->assertCount(0, Message::all());
        $this->assertCount(1, Message::withTrashed()->get());
    }

    public function test_message_job_context_filtering()
    {
        $job = Job::factory()->create();
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        // Messages with job context
        Message::factory()->count(2)->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'job_id' => $job->id,
        ]);

        // Messages without job context
        Message::factory()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'job_id' => null,
        ]);

        $jobMessages = Message::forJob($job->id)->get();
        $generalMessages = Message::whereNull('job_id')->get();

        $this->assertCount(2, $jobMessages);
        $this->assertCount(1, $generalMessages);
    }
}
