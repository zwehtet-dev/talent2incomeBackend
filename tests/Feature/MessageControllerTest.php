<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Job;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private User $thirdUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->otherUser = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $this->thirdUser = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    public function test_can_get_conversation_list(): void
    {
        // Clear any existing messages for this user to avoid test pollution
        Message::where('sender_id', $this->user->id)
            ->orWhere('recipient_id', $this->user->id)
            ->delete();

        // Create some messages
        Message::factory()->create([
            'sender_id' => $this->user->id,
            'recipient_id' => $this->otherUser->id,
            'content' => 'Hello there!',
            'created_at' => now()->subHours(2),
        ]);

        Message::factory()->create([
            'sender_id' => $this->otherUser->id,
            'recipient_id' => $this->user->id,
            'content' => 'Hi back!',
            'is_read' => false,
            'created_at' => now()->subHour(),
        ]);

        Message::factory()->create([
            'sender_id' => $this->thirdUser->id,
            'recipient_id' => $this->user->id,
            'content' => 'Another conversation',
            'is_read' => false,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/messages/conversations');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'participant' => ['id', 'first_name', 'last_name', 'avatar'],
                        'job',
                        'last_message' => ['content', 'created_at'],
                        'unread_count',
                        'message_count',
                    ],
                ],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
            ]);

        $data = $response->json('data');

        // Debug: Let's see what we actually get
        if (count($data) !== 2) {
            dump('Expected 2 conversations, got ' . count($data));
            dump('Conversations:', array_map(function ($conv) {
                return [
                    'participant_id' => $conv['participant']['id'],
                    'last_message' => $conv['last_message']['content'],
                    'message_count' => $conv['message_count'],
                ];
            }, $data));
        }

        $this->assertCount(2, $data);

        // Check that conversations are ordered by last message time
        $this->assertSame($this->thirdUser->id, $data[0]['participant']['id']);
        $this->assertSame($this->otherUser->id, $data[1]['participant']['id']);

        // Check unread counts
        $this->assertSame(1, $data[0]['unread_count']);
        $this->assertSame(1, $data[1]['unread_count']);
    }

    public function test_can_get_conversation_with_specific_user(): void
    {
        Message::factory()->create([
            'sender_id' => $this->user->id,
            'recipient_id' => $this->otherUser->id,
            'content' => 'First message',
            'created_at' => now()->subHours(2),
        ]);

        Message::factory()->create([
            'sender_id' => $this->otherUser->id,
            'recipient_id' => $this->user->id,
            'content' => 'Second message',
            'is_read' => false,
            'created_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/messages/conversation/{$this->otherUser->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'sender_id',
                        'recipient_id',
                        'content',
                        'is_read',
                        'created_at',
                        'sender',
                        'recipient',
                    ],
                ],
                'meta',
                'participant' => ['id', 'first_name', 'last_name', 'avatar'],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Check that messages are ordered by creation time (newest first)
        $this->assertSame('Second message', $data[0]['content']);
        $this->assertSame('First message', $data[1]['content']);

        // Check that unread message was marked as read
        $this->assertTrue(Message::where('sender_id', $this->otherUser->id)
            ->where('recipient_id', $this->user->id)
            ->first()
            ->is_read);
    }

    public function test_can_send_message(): void
    {
        $messageData = [
            'recipient_id' => $this->otherUser->id,
            'content' => 'Hello, this is a test message!',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages', $messageData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'sender_id',
                    'recipient_id',
                    'content',
                    'is_read',
                    'created_at',
                    'sender',
                    'recipient',
                ],
            ]);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->user->id,
            'recipient_id' => $this->otherUser->id,
            'is_read' => false,
        ]);

        // Verify content is encrypted in database
        $message = Message::latest()->first();
        $this->assertNotSame($messageData['content'], $message->getAttributes()['content']);
        $this->assertSame($messageData['content'], $message->content);
    }

    public function test_can_send_message_with_job_context(): void
    {
        $job = Job::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $messageData = [
            'recipient_id' => $this->otherUser->id,
            'job_id' => $job->id,
            'content' => 'Message about the job',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages', $messageData);

        $response->assertCreated();

        $this->assertDatabaseHas('messages', [
            'sender_id' => $this->user->id,
            'recipient_id' => $this->otherUser->id,
            'job_id' => $job->id,
        ]);
    }

    public function test_cannot_send_message_to_self(): void
    {
        $messageData = [
            'recipient_id' => $this->user->id,
            'content' => 'Message to myself',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages', $messageData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_id']);
    }

    public function test_cannot_send_message_with_empty_content(): void
    {
        $messageData = [
            'recipient_id' => $this->otherUser->id,
            'content' => '   ',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages', $messageData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_cannot_send_message_with_too_long_content(): void
    {
        $messageData = [
            'recipient_id' => $this->otherUser->id,
            'content' => str_repeat('a', 2001),
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages', $messageData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_can_mark_messages_as_read(): void
    {
        Message::factory()->create([
            'sender_id' => $this->otherUser->id,
            'recipient_id' => $this->user->id,
            'is_read' => false,
        ]);

        Message::factory()->create([
            'sender_id' => $this->otherUser->id,
            'recipient_id' => $this->user->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/messages/mark-read/{$this->otherUser->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Messages marked as read.',
                'updated_count' => 2,
            ]);

        $this->assertSame(0, Message::where('recipient_id', $this->user->id)
            ->where('is_read', false)
            ->count());
    }

    public function test_can_search_messages(): void
    {
        Message::factory()->create([
            'sender_id' => $this->user->id,
            'recipient_id' => $this->otherUser->id,
            'content' => 'This is about Laravel development',
        ]);

        Message::factory()->create([
            'sender_id' => $this->otherUser->id,
            'recipient_id' => $this->user->id,
            'content' => 'Vue.js is great for frontend',
        ]);

        Message::factory()->create([
            'sender_id' => $this->thirdUser->id,
            'recipient_id' => $this->user->id,
            'content' => 'Laravel is awesome',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/messages/search?query=Laravel');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'content',
                        'sender',
                        'recipient',
                        'job',
                    ],
                ],
                'meta',
                'search_query',
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $message) {
            $this->assertStringContainsStringIgnoringCase('Laravel', $message['content']);
        }
    }

    public function test_can_search_messages_with_specific_user(): void
    {
        Message::factory()->create([
            'sender_id' => $this->user->id,
            'recipient_id' => $this->otherUser->id,
            'content' => 'Laravel message to other user',
        ]);

        Message::factory()->create([
            'sender_id' => $this->thirdUser->id,
            'recipient_id' => $this->user->id,
            'content' => 'Laravel message from third user',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/messages/search?query=Laravel&user_id={$this->otherUser->id}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Laravel message to other user', $data[0]['content']);
    }

    public function test_search_requires_minimum_query_length(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/messages/search?query=a');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['query']);
    }

    public function test_can_block_user(): void
    {
        $blockData = [
            'user_id' => $this->otherUser->id,
            'reason' => 'Inappropriate behavior',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages/block', $blockData);

        $response->assertOk()
            ->assertJson(['message' => 'User blocked successfully.']);

        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->otherUser->id,
            'reason' => 'Inappropriate behavior',
        ]);
    }

    public function test_cannot_block_self(): void
    {
        $blockData = [
            'user_id' => $this->user->id,
            'reason' => 'Test',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages/block', $blockData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_block_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $blockData = [
            'user_id' => $admin->id,
            'reason' => 'Test',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages/block', $blockData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_can_unblock_user(): void
    {
        UserBlock::create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->otherUser->id,
            'reason' => 'Test block',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/messages/unblock/{$this->otherUser->id}");

        $response->assertOk()
            ->assertJson(['message' => 'User unblocked successfully.']);

        $this->assertDatabaseMissing('user_blocks', [
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->otherUser->id,
        ]);
    }

    public function test_can_get_blocked_users_list(): void
    {
        UserBlock::create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->otherUser->id,
            'reason' => 'Spam',
        ]);

        UserBlock::create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->thirdUser->id,
            'reason' => 'Inappropriate content',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/messages/blocked');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user' => ['id', 'first_name', 'last_name', 'avatar'],
                        'reason',
                        'blocked_at',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_cannot_send_message_to_blocked_user(): void
    {
        UserBlock::create([
            'blocker_id' => $this->otherUser->id,
            'blocked_id' => $this->user->id,
        ]);

        $messageData = [
            'recipient_id' => $this->otherUser->id,
            'content' => 'This should not be sent',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/messages', $messageData);

        $response->assertForbidden();
    }

    public function test_cannot_view_conversation_with_blocked_user(): void
    {
        UserBlock::create([
            'blocker_id' => $this->otherUser->id,
            'blocked_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/messages/conversation/{$this->otherUser->id}");

        $response->assertForbidden();
    }

    public function test_can_get_unread_message_count(): void
    {
        Message::factory()->create([
            'sender_id' => $this->otherUser->id,
            'recipient_id' => $this->user->id,
            'is_read' => false,
        ]);

        Message::factory()->create([
            'sender_id' => $this->thirdUser->id,
            'recipient_id' => $this->user->id,
            'is_read' => false,
        ]);

        Message::factory()->create([
            'sender_id' => $this->otherUser->id,
            'recipient_id' => $this->user->id,
            'is_read' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/messages/unread-count');

        $response->assertOk()
            ->assertJson(['unread_count' => 2]);
    }

    public function test_requires_authentication_for_all_endpoints(): void
    {
        $endpoints = [
            ['GET', '/api/messages/conversations'],
            ['GET', "/api/messages/conversation/{$this->otherUser->id}"],
            ['POST', '/api/messages'],
            ['POST', "/api/messages/mark-read/{$this->otherUser->id}"],
            ['GET', '/api/messages/search'],
            ['GET', '/api/messages/unread-count'],
            ['POST', '/api/messages/block'],
            ['DELETE', "/api/messages/unblock/{$this->otherUser->id}"],
            ['GET', '/api/messages/blocked'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $response->assertUnauthorized();
        }
    }

    public function test_requires_verified_email_to_send_messages(): void
    {
        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);

        $messageData = [
            'recipient_id' => $this->otherUser->id,
            'content' => 'Test message',
        ];

        $response = $this->actingAs($unverifiedUser, 'sanctum')
            ->postJson('/api/messages', $messageData);

        $response->assertForbidden();
    }

    public function test_pagination_works_for_conversations(): void
    {
        // Create conversations with multiple users
        for ($i = 0; $i < 20; $i++) {
            $user = User::factory()->create();
            Message::factory()->create([
                'sender_id' => $user->id,
                'recipient_id' => $this->user->id,
                'content' => "Message from user {$i}",
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/messages/conversations?per_page=10');

        $response->assertOk();

        $data = $response->json();
        $this->assertCount(10, $data['data']);
        $this->assertSame(1, $data['meta']['current_page']);
        $this->assertSame(20, $data['meta']['total']);
        $this->assertSame(2, $data['meta']['last_page']);
    }

    public function test_pagination_works_for_conversation_messages(): void
    {
        // Create many messages in a conversation
        for ($i = 0; $i < 30; $i++) {
            Message::factory()->create([
                'sender_id' => $i % 2 === 0 ? $this->user->id : $this->otherUser->id,
                'recipient_id' => $i % 2 === 0 ? $this->otherUser->id : $this->user->id,
                'content' => "Message {$i}",
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/messages/conversation/{$this->otherUser->id}?per_page=15");

        $response->assertOk();

        $data = $response->json();
        $this->assertCount(15, $data['data']);
        $this->assertSame(1, $data['meta']['current_page']);
        $this->assertSame(30, $data['meta']['total']);
        $this->assertSame(2, $data['meta']['last_page']);
    }
}
