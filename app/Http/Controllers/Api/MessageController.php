<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Message\BlockUserRequest;
use App\Http\Requests\Message\MessageSearchRequest;
use App\Http\Requests\Message\SendMessageRequest;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * Get conversation list for the authenticated user.
     */
    public function conversations(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Message::class);

        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 50);

        try {
            // Use a more direct approach with raw SQL to get conversation summaries
            $conversationData = [];

            // Get distinct conversations (other users + job combinations)
            $conversations = DB::select('
                SELECT 
                    CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END as other_user_id,
                    job_id,
                    MAX(created_at) as last_message_at,
                    COUNT(*) as message_count,
                    SUM(CASE WHEN recipient_id = ? AND is_read = 0 THEN 1 ELSE 0 END) as unread_count
                FROM messages 
                WHERE sender_id = ? OR recipient_id = ?
                GROUP BY 
                    CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END,
                    job_id
                ORDER BY last_message_at DESC
            ', [$user->id, $user->id, $user->id, $user->id, $user->id]);

            foreach ($conversations as $conv) {
                // Get the participant user
                $participant = User::select('id', 'first_name', 'last_name', 'avatar')
                    ->find($conv->other_user_id);

                // Get the job if exists
                $job = null;
                if ($conv->job_id) {
                    $job = DB::table('jobs')
                        ->select('id', 'title', 'status')
                        ->where('id', $conv->job_id)
                        ->first();
                }

                // Get the last message content
                $lastMessage = Message::where(function ($query) use ($user, $conv) {
                    $query->where('sender_id', $user->id)
                        ->where('recipient_id', $conv->other_user_id);
                })
                    ->orWhere(function ($query) use ($user, $conv) {
                        $query->where('sender_id', $conv->other_user_id)
                            ->where('recipient_id', $user->id);
                    })
                    ->when($conv->job_id, function ($query) use ($conv) {
                        $query->where('job_id', $conv->job_id);
                    })
                    ->when(! $conv->job_id, function ($query) {
                        $query->whereNull('job_id');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                $conversationData[] = [
                    'participant' => $participant,
                    'job' => $job,
                    'last_message' => [
                        'content' => $lastMessage ? (strlen($lastMessage->content) > 100 ? substr($lastMessage->content, 0, 97) . '...' : $lastMessage->content) : null,
                        'created_at' => $conv->last_message_at,
                    ],
                    'unread_count' => (int) $conv->unread_count,
                    'message_count' => (int) $conv->message_count,
                ];
            }

            // Manual pagination
            $total = count($conversationData);
            $currentPage = request()->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedData = array_slice($conversationData, $offset, $perPage);
            $lastPage = ceil($total / $perPage);

            return response()->json([
                'data' => $paginatedData,
                'meta' => [
                    'current_page' => (int) $currentPage,
                    'total' => $total,
                    'per_page' => $perPage,
                    'last_page' => $lastPage,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve conversations.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get conversation messages between authenticated user and another user.
     */
    public function conversation(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        $this->authorize('viewConversation', [Message::class, $user]);

        $jobId = $request->get('job_id');
        $perPage = min((int) $request->get('per_page', 20), 50);

        try {
            $query = Message::betweenUsers($currentUser->id, $user->id)
                ->withParticipants()
                ->orderBy('created_at', 'desc');

            if ($jobId) {
                $query->where('job_id', $jobId);
            }

            $messages = $query->paginate($perPage);

            // Mark messages as read
            Message::markConversationAsRead($currentUser->id, $user->id, $jobId);

            return response()->json([
                'data' => $messages->items(),
                'meta' => [
                    'current_page' => $messages->currentPage(),
                    'total' => $messages->total(),
                    'per_page' => $messages->perPage(),
                    'last_page' => $messages->lastPage(),
                ],
                'participant' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'avatar' => $user->avatar,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve conversation.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Send a new message.
     */
    public function store(SendMessageRequest $request): JsonResponse
    {
        $this->authorize('create', Message::class);

        $user = $request->user();
        $recipientId = $request->validated('recipient_id');
        $recipient = User::findOrFail($recipientId);

        // Check if user can send message to recipient
        $this->authorize('sendTo', [Message::class, $recipient]);

        try {
            $message = Message::create([
                'sender_id' => $user->id,
                'recipient_id' => $recipientId,
                'job_id' => $request->validated('job_id'),
                'content' => $request->validated('content'),
                'is_read' => false,
            ]);

            $message->load(['sender:id,first_name,last_name,avatar', 'recipient:id,first_name,last_name,avatar', 'job:id,title,status']);

            // Broadcast the message sent event
            broadcast(new MessageSent($message))->toOthers();

            return response()->json([
                'message' => 'Message sent successfully.',
                'data' => $message,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send message.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        $this->authorize('viewConversation', [Message::class, $user]);

        $jobId = $request->get('job_id');

        try {
            // Get messages before marking as read to broadcast events
            $messagesToMarkAsRead = Message::where('sender_id', $user->id)
                ->where('recipient_id', $currentUser->id)
                ->where('is_read', false)
                ->when($jobId, function ($query) use ($jobId) {
                    $query->where('job_id', $jobId);
                })
                ->get();

            $updatedCount = Message::markConversationAsRead($currentUser->id, $user->id, $jobId);

            // Broadcast message read events
            foreach ($messagesToMarkAsRead as $message) {
                broadcast(new MessageRead($message))->toOthers();
            }

            return response()->json([
                'message' => 'Messages marked as read.',
                'updated_count' => $updatedCount,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark messages as read.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Search messages within conversations.
     */
    public function search(MessageSearchRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Message::class);

        $user = $request->user();
        $query = $request->validated('query');
        $userId = $request->validated('user_id');
        $jobId = $request->validated('job_id');
        $perPage = $request->validated('per_page');

        try {
            // Get all messages for the user first, then filter by content after decryption
            $allMessages = Message::forUser($user->id)
                ->withParticipants()
                ->withJob()
                ->orderBy('created_at', 'desc')
                ->get();

            // Filter messages by search query (after decryption)
            $filteredMessages = $allMessages->filter(function ($message) use ($query) {
                return stripos($message->content, $query) !== false;
            });

            // Apply additional filters
            if ($userId) {
                $filteredMessages = $filteredMessages->filter(function ($message) use ($user, $userId) {
                    return ($message->sender_id === $user->id && $message->recipient_id === $userId) ||
                           ($message->sender_id === $userId && $message->recipient_id === $user->id);
                });
            }

            if ($jobId) {
                $filteredMessages = $filteredMessages->filter(function ($message) use ($jobId) {
                    return $message->job_id === $jobId;
                });
            }

            // Paginate the filtered results manually
            $total = $filteredMessages->count();
            $currentPage = request()->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedMessages = $filteredMessages->slice($offset, $perPage)->values();

            $lastPage = ceil($total / $perPage);

            if ($userId) {
                $messagesQuery->where(function ($q) use ($user, $userId) {
                    $q->where(function ($subQ) use ($user, $userId) {
                        $subQ->where('sender_id', $user->id)->where('recipient_id', $userId);
                    })->orWhere(function ($subQ) use ($user, $userId) {
                        $subQ->where('sender_id', $userId)->where('recipient_id', $user->id);
                    });
                });
            }

            if ($jobId) {
                $messagesQuery->where('job_id', $jobId);
            }

            return response()->json([
                'data' => $paginatedMessages,
                'meta' => [
                    'current_page' => (int) $currentPage,
                    'total' => $total,
                    'per_page' => $perPage,
                    'last_page' => $lastPage,
                ],
                'search_query' => $query,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to search messages.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Block a user.
     */
    public function blockUser(BlockUserRequest $request): JsonResponse
    {
        $user = $request->user();
        $userIdToBlock = $request->validated('user_id');
        $reason = $request->validated('reason');

        try {
            $blocked = UserBlock::blockUser($user->id, $userIdToBlock, $reason);

            if ($blocked) {
                return response()->json([
                    'message' => 'User blocked successfully.',
                ]);
            }

            return response()->json([
                'message' => 'Failed to block user.',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to block user.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Unblock a user.
     */
    public function unblockUser(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        $request->validate([
            // No additional validation needed as user is resolved from route
        ]);

        try {
            $unblocked = UserBlock::unblockUser($currentUser->id, $user->id);

            if ($unblocked) {
                return response()->json([
                    'message' => 'User unblocked successfully.',
                ]);
            }

            return response()->json([
                'message' => 'User was not blocked or failed to unblock.',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to unblock user.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get list of blocked users.
     */
    public function blockedUsers(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $blockedUsers = UserBlock::getBlockedUsers($user->id);

            return response()->json([
                'data' => $blockedUsers->map(function ($block) {
                    return [
                        'id' => $block->id,
                        'user' => $block->blocked,
                        'reason' => $block->reason,
                        'blocked_at' => $block->created_at,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve blocked users.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get unread message count for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $unreadCount = Message::where('recipient_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'unread_count' => $unreadCount,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get unread count.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Send typing indicator.
     */
    public function typing(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        $this->authorize('sendTo', [Message::class, $user]);

        $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        try {
            $isTyping = $request->boolean('is_typing');

            // Broadcast typing indicator
            broadcast(new UserTyping($currentUser, $user->id, $isTyping))->toOthers();

            return response()->json([
                'message' => 'Typing indicator sent.',
                'is_typing' => $isTyping,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send typing indicator.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
