<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;

it('requires authentication for project listing', function (): void {
    $this->getJson('/api/v1/projects')->assertStatus(401);
});

it('can list own projects', function (): void {
    $user = User::factory()->create();
    Project::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/v1/projects')
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

it('only lists own projects', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Project::factory()->count(2)->create(['user_id' => $user1->id]);
    Project::factory()->count(4)->create(['user_id' => $user2->id]);

    $response = $this->actingAs($user1)
        ->getJson('/api/v1/projects')
        ->assertStatus(200);

    expect($response->json('data'))->toHaveCount(2);
});

it('can create a project', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/v1/projects', ['name' => 'My Project'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'My Project');
});

it('can view own project', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $project->id);
});

it('cannot view another users project', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user1->id]);

    $this->actingAs($user2)
        ->getJson("/api/v1/projects/{$project->id}")
        ->assertStatus(403);
});

it('can update own project', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->putJson("/api/v1/projects/{$project->id}", ['name' => 'Updated Project'])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Updated Project');
});

it('cannot update another users project', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user2->assignRole('user');
    $project = Project::factory()->create(['user_id' => $user1->id]);

    $this->actingAs($user2)
        ->putJson("/api/v1/projects/{$project->id}", ['name' => 'Hacked'])
        ->assertStatus(403);
});

it('can delete own project', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');
    $project = Project::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertStatus(204);
});

it('cannot delete another users project', function (): void {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user2->assignRole('user');
    $project = Project::factory()->create(['user_id' => $user1->id]);

    $this->actingAs($user2)
        ->deleteJson("/api/v1/projects/{$project->id}")
        ->assertStatus(403);
});

it('rejects project creation without required fields', function (): void {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->postJson('/api/v1/projects', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
