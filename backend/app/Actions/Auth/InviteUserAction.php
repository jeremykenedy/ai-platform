<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InviteUserAction
{
    /**
     * Create an invited user record with a unique invite token.
     *
     * The invite URL for the new user will be {APP_URL}/register/{invite_token}.
     */
    public function handle(string $email, string $name, string $role = 'user'): User
    {
        $inviteToken = Str::random(64);

        /** @var User $user */
        $user = User::create([
            'email' => $email,
            'name' => $name,
            'invite_token' => $inviteToken,
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
