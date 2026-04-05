<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TrainingJob;
use App\Models\User;

class TrainingJobPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasRole('admin') && $user->hasPermissionTo('training.manage')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('training.view');
    }

    public function view(User $user, TrainingJob $job): bool
    {
        return $job->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('training.manage');
    }

    public function cancel(User $user, TrainingJob $job): bool
    {
        return $job->user_id === $user->id;
    }
}
