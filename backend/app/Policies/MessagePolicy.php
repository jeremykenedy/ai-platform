<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    public function view(User $user, Message $message): bool
    {
        return $message->conversation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('conversations.create');
    }

    public function delete(User $user, Message $message): bool
    {
        return $message->conversation->user_id === $user->id;
    }
}
