<?php

declare(strict_types=1);
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

it('has correct fillable attributes', function (): void {
    $user = new User;
    expect($user->getFillable())->toContain('name', 'email', 'password', 'avatar', 'locale', 'timezone', 'invite_token', 'invited_by');
});

it('has correct hidden attributes', function (): void {
    $user = new User;
    expect($user->getHidden())->toContain('password', 'remember_token', 'invite_token');
});

it('has correct casts', function (): void {
    $user = new User;
    $casts = $user->getCasts();
    expect($casts)
        ->toHaveKey('email_verified_at', 'datetime')
        ->toHaveKey('password', 'hashed')
        ->toHaveKey('last_active_at', 'datetime');
});

it('uses ulids', function (): void {
    expect(in_array(HasUlids::class, class_uses_recursive(User::class), true))->toBeTrue();
});
