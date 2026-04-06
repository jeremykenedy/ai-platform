<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;

it('requires authentication for conversation listing', function (): void {
    $this->getJson('/api/v1/conversations')->assertStatus(401);
});

it('requires authentication for conversation creation', function (): void {
    $this->postJson('/api/v1/conversations', ['title' => 'Test'])->assertStatus(401);
});

it('can list own conversations', function (): void {
    $user = User::factory()->create();
    Conversation::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/v1/conversations')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

it('only lists own conversations', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Conversation::factory()->count(2)->create(['user_id' => $user1->id]);
    Conversation::factory()->count(3)->create(['user_id' => $user2->id]);

    $response = $this->actingAs($user1)
        ->getJson('/api/v1/conversations')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(2);
});

it('can create a conversation', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/v1/conversations', ['title' => 'Test Chat'])
        ->assertStatus(201)
        ->assertJsonPath('data.title', 'Test Chat');
});

it('can view own conversation', function (): void {
    $user = User::factory()->create();
    $conv = Conversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/conversations/{$conv->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $conv->id);
});

it('cannot view another users conversation', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conv = Conversation::factory()->create(['user_id' => $user1->id]);

    $this->actingAs($user2)
        ->getJson("/api/v1/conversations/{$conv->id}")
        ->assertStatus(403);
});

it('can update own conversation', function (): void {
    $user = User::factory()->create();
    $conv = Conversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/api/v1/conversations/{$conv->id}", ['title' => 'Updated Title'])
        ->assertStatus(200)
        ->assertJsonPath('data.title', 'Updated Title');
});

it('cannot update another users conversation', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $conv = Conversation::factory()->create(['user_id' => $user1->id]);

    $this->actingAs($user2)
        ->putJson("/api/v1/conversations/{$conv->id}", ['title' => 'Hacked'])
        ->assertStatus(403);
});

it('can delete own conversation', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $conv = Conversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/conversations/{$conv->id}")
        ->assertStatus(204);
});

it('cannot delete another users conversation', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user2->assignRole('user');
    $conv = Conversation::factory()->create(['user_id' => $user1->id]);

    $this->actingAs($user2)
        ->deleteJson("/api/v1/conversations/{$conv->id}")
        ->assertStatus(403);
});

it('returns 404 for non-existent conversation', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/conversations/non-existent-id')
        ->assertStatus(404);
});
