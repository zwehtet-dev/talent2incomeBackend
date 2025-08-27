<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private user channel for receiving personal notifications and messages
Broadcast::channel('user.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});

// Private conversation channel for real-time messaging between two users
Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    // Parse conversation ID (format: "userId1-userId2")
    $userIds = explode('-', $conversationId);

    if (count($userIds) !== 2) {
        return false;
    }

    $userId1 = (int) $userIds[0];
    $userId2 = (int) $userIds[1];

    // User can join if they are one of the participants
    return $user->id === $userId1 || $user->id === $userId2;
});

// Presence channel for tracking online users
Broadcast::channel('online-users', function (User $user) {
    return [
        'id' => $user->id,
        'name' => $user->first_name . ' ' . $user->last_name,
        'avatar' => $user->avatar,
    ];
});

// Private channel for job-specific notifications
Broadcast::channel('job.{jobId}', function (User $user, int $jobId) {
    // Check if user is the job owner or assigned to the job
    $job = \App\Models\Job::find($jobId);

    if (! $job) {
        return false;
    }

    return $user->id === $job->user_id || $user->id === $job->assigned_to;
});

// Admin channel for administrative notifications
Broadcast::channel('admin', function (User $user) {
    return $user->is_admin;
});
