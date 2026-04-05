<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\UserIntegration;

class UserIntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function manage(User $user, UserIntegration $integration): bool
    {
        return $integration->user_id === $user->id;
    }

    public function connect(User $user): bool
    {
        return true;
    }

    public function disconnect(User $user, UserIntegration $integration): bool
    {
        return $integration->user_id === $user->id;
    }
}
