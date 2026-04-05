<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    /**
     * Attempt to authenticate the user and return the session data.
     *
     * @return array{user: User, token: null}
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password): array
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        $user->update(['last_active_at' => now()]);

        return [
            'user' => $user,
            'token' => null,
        ];
    }
}
