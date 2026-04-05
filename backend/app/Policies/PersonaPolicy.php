<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Persona;
use App\Models\User;

class PersonaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasRole('admin') && $user->hasPermissionTo('personas.manage-all') && in_array($ability, ['update', 'delete'], true)) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Persona $persona): bool
    {
        return $persona->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('personas.create');
    }

    public function update(User $user, Persona $persona): bool
    {
        return $persona->user_id === $user->id && $user->hasPermissionTo('personas.manage-own');
    }

    public function delete(User $user, Persona $persona): bool
    {
        return $persona->user_id === $user->id && $user->hasPermissionTo('personas.manage-own');
    }
}
