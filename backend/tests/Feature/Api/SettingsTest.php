<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSetting;

it('requires authentication to get settings', function (): void {
    $this->getJson('/api/v1/settings')->assertStatus(401);
});

it('requires authentication to update settings', function (): void {
    $this->putJson('/api/v1/settings', [])->assertStatus(401);
});

it('can get settings when none exist yet', function (): void {
    $user = User::factory()->create();

    // First access creates the settings record, returns 201
    $this->actingAs($user)
        ->getJson('/api/v1/settings')
        ->assertSuccessful();
});

it('can get settings when they already exist', function (): void {
    $user = User::factory()->create();
    UserSetting::create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/v1/settings')
        ->assertStatus(200);
});

it('can update settings with valid data', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/v1/settings', [
            'theme'     => 'dark',
            'font_size' => 16,
        ])->assertSuccessful();
});

it('settings response contains expected fields', function (): void {
    $user = User::factory()->create();
    UserSetting::create(['user_id' => $user->id, 'theme' => 'dark']);

    $this->actingAs($user)
        ->getJson('/api/v1/settings')
        ->assertStatus(200)
        ->assertJsonStructure(['data' => ['theme', 'font_size']]);
});

it('rejects settings update with invalid theme', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/v1/settings', ['theme' => 'invalid-theme'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['theme']);
});

it('rejects settings update with out-of-range font size', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/v1/settings', ['font_size' => 99])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['font_size']);
});

it('accessing settings when they exist returns a 200', function (): void {
    $user = User::factory()->create();
    UserSetting::create(['user_id' => $user->id]);

    // Pre-existing settings: not wasRecentlyCreated, returns 200
    $this->actingAs($user)->getJson('/api/v1/settings')->assertStatus(200);
    $this->actingAs($user)->getJson('/api/v1/settings')->assertStatus(200);
});
