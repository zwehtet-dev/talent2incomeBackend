<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MessagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Users can only view their own conversations
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Message $message): bool
    {
        // Users can only view messages they sent or received
        return $user->id === $message->sender_id ||
               $user->id === $message->recipient_id ||
               $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only verified and active users can send messages
        return $user->is_active && $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Message $message): bool
    {
        // Messages cannot be updated after creation for security
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Message $message): bool
    {
        // Only sender can delete their own messages within 24 hours, admins can delete any
        $canDeleteOwnMessage = $user->id === $message->sender_id &&
                              $message->created_at->diffInHours(now()) < 24;

        return $canDeleteOwnMessage || $user->is_admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Message $message): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Message $message): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can send a message to another user.
     */
    public function sendTo(User $user, User $recipient): bool
    {
        // Users cannot message themselves, recipient must be active, sender must be verified
        if ($user->id === $recipient->id || ! $recipient->is_active) {
            return false;
        }

        // Check if recipient has blocked the sender
        if ($this->isBlocked($user, $recipient)) {
            return false;
        }

        return $user->is_active && $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can mark messages as read.
     */
    public function markAsRead(User $user, Message $message): bool
    {
        // Only recipient can mark messages as read
        return $user->id === $message->recipient_id;
    }

    /**
     * Determine whether the user can view conversation with another user.
     */
    public function viewConversation(User $user, User $otherUser): bool
    {
        // Users can view conversations they are part of, unless blocked
        return $user->id !== $otherUser->id &&
               ! $this->isBlocked($user, $otherUser) &&
               $user->is_active &&
               $user->email_verified_at !== null;
    }

    /**
     * Determine whether the user can report a message.
     */
    public function report(User $user, Message $message): bool
    {
        // Users can report messages they received
        return $user->id === $message->recipient_id;
    }

    /**
     * Determine whether the user can moderate messages.
     */
    public function moderate(User $user, Message $message): bool
    {
        return $user->is_admin;
    }

    /**
     * Check if a user is blocked by another user.
     */
    private function isBlocked(User $user, User $otherUser): bool
    {
        return \App\Models\UserBlock::isMutuallyBlocked($user->id, $otherUser->id);
    }
}
