<?php

declare(strict_types=1);
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

it('has correct fillable attributes', function (): void {
    $conversation = new Conversation;
    expect($conversation->getFillable())->toContain(
        'user_id',
        'project_id',
        'persona_id',
        'title',
        'model_name',
        'context_window_used',
        'enabled_integrations',
    );
});

it('has correct casts', function (): void {
    $conversation = new Conversation;
    $casts = $conversation->getCasts();
    expect($casts)
        ->toHaveKey('enabled_integrations', 'array')
        ->toHaveKey('context_window_used', 'integer');
});

it('uses ulids', function (): void {
    expect(in_array(HasUlids::class, class_uses_recursive(Conversation::class), true))->toBeTrue();
});

it('uses soft deletes', function (): void {
    expect(in_array(SoftDeletes::class, class_uses_recursive(Conversation::class), true))->toBeTrue();
});
