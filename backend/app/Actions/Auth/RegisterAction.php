<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterAction
{
    /**
     * Register a new user via an invite token.
     *
     * @param  array{name: string, email: string, password: string, invite_token: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data): User
    {
        $inviteToken = (string) ($data['invite_token'] ?? '');

        $invitingUser = User::where('invite_token', $inviteToken)
            ->whereNotNull('invite_token')
            ->first();

        if ($invitingUser === null) {
            throw ValidationException::withMessages([
                'invite_token' => ['Invalid or expired invite token.'],
            ]);
        }

        /** @var User $user */
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'invited_by' => (string) $invitingUser->id,
        ]);

        $invitingUser->update(['invite_token' => null]);

        $user->assignRole('user');

        UserSetting::create(['user_id' => $user->id]);

        event(new Registered($user));

        return $user;
    }
}
