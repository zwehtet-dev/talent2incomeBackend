<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Message;
use App\Models\User;
use App\Services\OnlineStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SimpleBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_typing_indicator_endpoint_works(): void
    {
        $user1 = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user2 = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        $this->actingAs($user1, 'sanctum');

        $response = $this->postJson("/api/messages/typing/{$user2->id}", [
            'is_typing' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Typing indicator sent.',
                'is_typing' => true,
            ]);

        Event::assertDispatched(UserTyping::class);
    }

    public function test_online_status_service_works(): void
    {
        $user = User::factory()->create();
        $onlineStatusService = app(OnlineStatusService::class);

        // Initially user should not be online
        $this->assertFalse($onlineStatusService->isUserOnline($user->id));

        // Mark user as online
        $onlineStatusService->markUserOnline($user);
        $this->assertTrue($onlineStatusService->isUserOnline($user->id));

        // Mark user as offline
        $onlineStatusService->markUserOffline($user);
        $this->assertFalse($onlineStatusService->isUserOnline($user->id));
    }

    public function test_message_events_have_correct_structure(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $message = Message::factory()->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'content' => 'Test message',
        ]);

        $event = new MessageSent($message);

        $this->assertSame($message->id, $event->message->id);
        $this->assertIsArray($event->broadcastWith());
        $this->assertArrayHasKey('id', $event->broadcastWith());
        $this->assertArrayHasKey('content', $event->broadcastWith());
        $this->assertArrayHasKey('sender', $event->broadcastWith());
    }

    public function test_typing_event_has_correct_structure(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $event = new UserTyping($user1, $user2->id, true);

        $this->assertSame($user1->id, $event->user->id);
        $this->assertSame($user2->id, $event->recipientId);
        $this->assertTrue($event->isTyping);

        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('user_id', $broadcastData);
        $this->assertArrayHasKey('is_typing', $broadcastData);
        $this->assertArrayHasKey('timestamp', $broadcastData);
    }
}
