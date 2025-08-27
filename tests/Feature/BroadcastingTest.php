<?php

namespace Tests\Feature;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserOnlineStatusChanged;
use App\Events\UserTyping;
use App\Models\Message;
use App\Models\User;
use App\Services\OnlineStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_message_sent_event_is_broadcasted_when_message_is_created(): void
    {
        $sender = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $recipient = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        $this->actingAs($sender, 'sanctum');

        $response = $this->postJson('/api/messages', [
            'recipient_id' => $recipient->id,
            'content' => 'Hello, this is a test message!',
        ]);

        $response->assertStatus(201);
        Event::assertDispatched(MessageSent::class);
    }

    public function test_message_read_event_is_broadcasted_when_messages_are_marked_as_read(): void
    {
        $sender = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $recipient = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        // Create unread messages
        Message::factory()->count(3)->create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'is_read' => false,
        ]);

        $this->actingAs($recipient, 'sanctum');

        $response = $this->postJson("/api/messages/mark-read/{$sender->id}");

        $response->assertStatus(200);
        Event::assertDispatched(MessageRead::class, 3);
    }

    public function test_typing_indicator_is_broadcasted(): void
    {
        $user1 = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $user2 = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        $this->actingAs($user1, 'sanctum');

        $response = $this->postJson("/api/messages/typing/{$user2->id}", [
            'is_typing' => true,
        ]);

        $response->assertStatus(200);
        Event::assertDispatched(UserTyping::class);
    }

    public function test_user_online_status_changed_event_is_broadcasted(): void
    {
        $user = User::factory()->create();
        $onlineStatusService = app(OnlineStatusService::class);

        $onlineStatusService->markUserOnline($user);

        Event::assertDispatched(UserOnlineStatusChanged::class, function ($event) use ($user) {
            return $event->user->id === $user->id && $event->isOnline === true;
        });
    }

    public function test_user_can_access_private_channel_for_their_own_messages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-user.{$user->id}",
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(200);
    }

    public function test_user_cannot_access_private_channel_for_other_users(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->actingAs($user1, 'sanctum');

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-user.{$user2->id}",
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_access_conversation_channel_they_participate_in(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create conversation ID (sorted user IDs)
        $userIds = [$user1->id, $user2->id];
        sort($userIds);
        $conversationId = implode('-', $userIds);

        $this->actingAs($user1, 'sanctum');

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-conversation.{$conversationId}",
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(200);
    }

    public function test_user_cannot_access_conversation_channel_they_dont_participate_in(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Create conversation ID between user2 and user3
        $userIds = [$user2->id, $user3->id];
        sort($userIds);
        $conversationId = implode('-', $userIds);

        $this->actingAs($user1, 'sanctum');

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-conversation.{$conversationId}",
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_access_online_users_presence_channel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'presence-online-users',
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertTrue(isset($responseData['auth']) || isset($responseData['channel_data']));
    }

    public function test_admin_can_access_admin_channel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-admin',
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_admin_channel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-admin',
            'socket_id' => '123.456',
        ]);

        $response->assertStatus(403);
    }

    public function test_online_users_endpoint_returns_correct_data(): void
    {
        $user = User::factory()->create();
        $onlineStatusService = app(OnlineStatusService::class);

        // Mark user as online
        $onlineStatusService->markUserOnline($user);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/users/online');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'avatar',
                        'location',
                        'is_online',
                        'last_seen',
                    ],
                ],
                'meta',
                'total_online',
            ]);
    }

    public function test_user_online_status_endpoint_returns_correct_data(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $onlineStatusService = app(OnlineStatusService::class);

        // Mark user2 as online
        $onlineStatusService->markUserOnline($user2);

        $this->actingAs($user1, 'sanctum');

        $response = $this->getJson("/api/users/{$user2->id}/online-status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user_id',
                'is_online',
                'last_seen',
                'last_seen_human',
            ])
            ->assertJson([
                'user_id' => $user2->id,
                'is_online' => true,
            ]);
    }
}
