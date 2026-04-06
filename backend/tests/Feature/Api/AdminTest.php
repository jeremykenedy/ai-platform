<?php

declare(strict_types=1);

use App\Models\User;

it('requires authentication for admin endpoints', function (): void {
    $this->getJson('/api/v1/admin/users')->assertStatus(401);
});

it('requires admin role for user listing', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->getJson('/api/v1/admin/users')
        ->assertStatus(403);
});

it('requires admin role for dashboard', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->getJson('/api/v1/admin/dashboard')
        ->assertStatus(403);
});

it('allows admin to list users', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/users')
        ->assertStatus(200);
});

it('allows admin to access dashboard', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/dashboard')
        ->assertStatus(200)
        ->assertJsonStructure(['data' => [
            'total_users',
            'total_conversations',
            'total_messages',
            'active_models_count',
            'running_jobs_count',
        ]]);
});

it('allows super-admin to list users', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $this->actingAs($superAdmin)
        ->getJson('/api/v1/admin/users')
        ->assertStatus(200);
});

it('allows super-admin to access dashboard', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $this->actingAs($superAdmin)
        ->getJson('/api/v1/admin/dashboard')
        ->assertStatus(200);
});
