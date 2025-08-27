<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Message extends Model
{
    use HasFactory;
    use \App\Traits\CacheInvalidation;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sender_id',
        'recipient_id',
        'job_id',
        'content',
        'is_read',
    ];

    /**
     * The sender of this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * The recipient of this message.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * The job this message is related to.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Encrypt the content before saving.
     */
    public function setContentAttribute(string $value): void
    {
        $this->attributes['content'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt the content when retrieving.
     */
    public function getContentAttribute(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // If decryption fails, return the original value
            // This handles cases where content might not be encrypted (legacy data)
            return $value;
        }
    }

    /**
     * Get the conversation ID for this message.
     * This creates a consistent identifier for conversations between two users.
     */
    public function getConversationIdAttribute(): string
    {
        $userIds = [$this->sender_id, $this->recipient_id];
        sort($userIds);

        return implode('-', $userIds) . ($this->job_id ? "-job-{$this->job_id}" : '');
    }

    /**
     * Get the other participant in this conversation.
     */
    public function getOtherParticipant(int $currentUserId): User
    {
        return $this->sender_id === $currentUserId ? $this->recipient : $this->sender;
    }

    /**
     * Mark this message as read.
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        $this->is_read = true;

        return $this->save();
    }

    /**
     * Check if message can be read by user.
     */
    public function canBeReadBy(int $userId): bool
    {
        return $this->sender_id === $userId || $this->recipient_id === $userId;
    }

    /**
     * Get a preview of the message content (first 100 characters).
     */
    public function getPreviewAttribute(): string
    {
        $content = $this->content;

        if (strlen($content) <= 100) {
            return $content;
        }

        return substr($content, 0, 97) . '...';
    }

    /**
     * Check if this message is from the current user.
     */
    public function isFromUser(int $userId): bool
    {
        return $this->sender_id === $userId;
    }

    /**
     * Scope to filter messages between two users.
     * @param mixed $query
     */
    public function scopeBetweenUsers($query, int $user1Id, int $user2Id)
    {
        return $query->where(function ($q) use ($user1Id, $user2Id) {
            $q->where('sender_id', $user1Id)->where('recipient_id', $user2Id);
        })->orWhere(function ($q) use ($user1Id, $user2Id) {
            $q->where('sender_id', $user2Id)->where('recipient_id', $user1Id);
        });
    }

    /**
     * Scope to filter messages for a specific user.
     * @param mixed $query
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('sender_id', $userId)
            ->orWhere('recipient_id', $userId);
    }

    /**
     * Scope to filter unread messages.
     * @param mixed $query
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to filter messages by job.
     * @param mixed $query
     */
    public function scopeForJob($query, int $jobId)
    {
        return $query->where('job_id', $jobId);
    }

    /**
     * Scope to get conversation threads.
     * @param mixed $query
     */
    public function scopeConversationThreads($query, int $userId)
    {
        return $query->selectRaw('
                CASE 
                    WHEN sender_id = ? THEN recipient_id 
                    ELSE sender_id 
                END as other_user_id,
                job_id,
                MAX(created_at) as last_message_at,
                COUNT(*) as message_count,
                SUM(CASE WHEN recipient_id = ? AND is_read = 0 THEN 1 ELSE 0 END) as unread_count
            ', [$userId, $userId])
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                    ->orWhere('recipient_id', $userId);
            })
            ->groupByRaw('
                CASE 
                    WHEN sender_id = ? THEN recipient_id 
                    ELSE sender_id 
                END,
                job_id
            ', [$userId])
            ->orderBy('last_message_at', 'desc');
    }

    /**
     * Scope to include sender and recipient relationships.
     * @param mixed $query
     */
    public function scopeWithParticipants($query)
    {
        return $query->with([
            'sender:id,first_name,last_name,avatar',
            'recipient:id,first_name,last_name,avatar',
        ]);
    }

    /**
     * Scope to include job relationship.
     * @param mixed $query
     */
    public function scopeWithJob($query)
    {
        return $query->with('job:id,title,status');
    }

    /**
     * Get messages in a conversation with pagination support.
     */
    public static function getConversation(int $user1Id, int $user2Id, ?int $jobId = null, int $perPage = 20)
    {
        $query = static::betweenUsers($user1Id, $user2Id)
            ->withParticipants()
            ->orderBy('created_at', 'desc');

        if ($jobId) {
            $query->where('job_id', $jobId);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get conversation list for a user.
     */
    public static function getConversationList(int $userId, int $perPage = 15)
    {
        return static::conversationThreads($userId)
            ->paginate($perPage);
    }

    /**
     * Mark all messages in a conversation as read.
     */
    public static function markConversationAsRead(int $currentUserId, int $otherUserId, ?int $jobId = null): int
    {
        $query = static::where('recipient_id', $currentUserId)
            ->where('sender_id', $otherUserId)
            ->where('is_read', false);

        if ($jobId) {
            $query->where('job_id', $jobId);
        }

        return $query->update(['is_read' => true]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }
}
