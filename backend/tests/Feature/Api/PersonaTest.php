<?php

declare(strict_types=1);

use App\Models\Persona;
use App\Models\User;

it('requires authentication for persona listing', function (): void {
    $this->getJson('/api/v1/personas')->assertStatus(401);
});

it('can list own personas', function (): void {
    $user = User::factory()->create();
    Persona::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/v1/personas')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

it('only lists own personas', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Persona::factory()->count(2)->create(['user_id' => $user1->id]);
    Persona::factory()->count(3)->create(['user_id' => $user2->id]);

    $response = $this->actingAs($user1)
        ->getJson('/api/v1/personas')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(2);
});

it('can create a persona', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/v1/personas', [
            'name'          => 'Test Persona',
            'system_prompt' => 'You are a helpful assistant.',
        ])->assertStatus(201)
        ->assertJsonPath('data.name', 'Test Persona');
});

it('can view own persona', function (): void {
    $user = User::factory()->create();
    $persona = Persona::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/personas/{$persona->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $persona->id);
});

it('cannot view another users persona', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $persona = Persona::factory()->create(['user_id' => $user1->id]);

    $this->actingAs($user2)
        ->getJson("/api/v1/personas/{$persona->id}")
        ->assertStatus(403);
});

it('can update own persona', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $persona = Persona::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/api/v1/personas/{$persona->id}", ['name' => 'Updated'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated');
});

it('cannot update another users persona', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user2->assignRole('user');
    $persona = Persona::factory()->create(['user_id' => $user1->id]);

    $this->actingAs($user2)
        ->putJson("/api/v1/personas/{$persona->id}", ['name' => 'Hacked'])
        ->assertStatus(403);
});

it('can delete own persona', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $persona = Persona::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/personas/{$persona->id}")
        ->assertStatus(204);
});

it('cannot delete another users persona', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user2->assignRole('user');
    $persona = Persona::factory()->create(['user_id' => $user1->id]);

    $this->actingAs($user2)
        ->deleteJson("/api/v1/personas/{$persona->id}")
        ->assertStatus(403);
});

it('rejects persona creation without required fields', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/v1/personas', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'system_prompt']);
});
