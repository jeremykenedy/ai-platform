<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiModel;
use App\Models\User;

class AiModelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('models.view');
    }

    public function view(User $user, AiModel $model): bool
    {
        return $user->hasPermissionTo('models.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermissionTo('models.manage');
    }

    public function pull(User $user): bool
    {
        return $user->hasPermissionTo('models.manage');
    }

    public function delete(User $user, AiModel $model): bool
    {
        return $user->hasPermissionTo('models.manage');
    }
}
