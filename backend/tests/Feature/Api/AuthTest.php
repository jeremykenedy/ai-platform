<?php

declare(strict_types=1);

use App\Models\User;

it('returns 401 for unauthenticated user endpoint', function (): void {
    $this->getJson('/api/v1/auth/user')->assertStatus(401);
});

it('can login with valid credentials', function (): void {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(200)
        ->assertJsonStructure(['data' => ['id', 'name', 'email']]);
});

it('rejects login with invalid credentials', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(422);
});

it('can logout', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/v1/auth/logout')
        ->assertStatus(204);
});

it('returns authenticated user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/auth/user')
        ->assertStatus(200)
        ->assertJsonPath('data.email', $user->email);
});

it('rejects login with missing fields', function (): void {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('rejects registration without invite token', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422);
});

it('rejects registration with invalid invite token', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'invite_token' => 'invalid-token-that-does-not-exist',
    ])->assertStatus(422);
});
