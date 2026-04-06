<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    public function rules(): array
    {
        $routeUser = $this->route('user');
        $userId = ($routeUser instanceof User) ? $routeUser->id : $this->route('id');

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['nullable', 'string', 'in:user,admin,super-admin'],
            'subscription_tier' => ['nullable', 'string'],
        ];
    }
}
