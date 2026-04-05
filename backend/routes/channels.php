<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    $conversation = Conversation::find($conversationId);

    return $conversation && $conversation->user_id === $user->id;
});

Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});

Broadcast::channel('admin', function (User $user) {
    return $user->hasRole(['admin', 'super-admin']);
});
