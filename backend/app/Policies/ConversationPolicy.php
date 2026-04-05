<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasRole('admin') && $user->hasPermissionTo('conversations.view-all') && in_array($ability, ['viewAny', 'view'], true)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('conversations.create');
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $conversation->user_id === $user->id && $user->hasPermissionTo('conversations.delete');
    }

    public function export(User $user, Conversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }
}
