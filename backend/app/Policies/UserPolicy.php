<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('users.manage');
    }

    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->hasPermissionTo('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->hasPermissionTo('users.manage');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermissionTo('users.manage') && $user->id !== $target->id;
    }

    public function invite(User $user): bool
    {
        return $user->hasPermissionTo('users.manage');
    }
}
