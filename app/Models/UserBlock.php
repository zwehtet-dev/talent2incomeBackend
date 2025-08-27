<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'blocker_id',
        'blocked_id',
        'reason',
    ];

    /**
     * The user who initiated the block.
     */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /**
     * The user who was blocked.
     */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    /**
     * Check if a user has blocked another user.
     */
    public static function isBlocked(int $blockerId, int $blockedId): bool
    {
        return static::where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->exists();
    }

    /**
     * Check if two users have blocked each other (mutual block).
     */
    public static function isMutuallyBlocked(int $user1Id, int $user2Id): bool
    {
        return static::isBlocked($user1Id, $user2Id) || static::isBlocked($user2Id, $user1Id);
    }

    /**
     * Get all users blocked by a specific user.
     */
    public static function getBlockedUsers(int $blockerId)
    {
        return static::where('blocker_id', $blockerId)
            ->with('blocked:id,first_name,last_name,avatar')
            ->get();
    }

    /**
     * Block a user.
     */
    public static function blockUser(int $blockerId, int $blockedId, ?string $reason = null): bool
    {
        return static::updateOrCreate(
            [
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
            ],
            [
                'reason' => $reason,
            ]
        ) !== null;
    }

    /**
     * Unblock a user.
     */
    public static function unblockUser(int $blockerId, int $blockedId): bool
    {
        return static::where('blocker_id', $blockerId)
            ->where('blocked_id', $blockedId)
            ->delete() > 0;
    }
}
