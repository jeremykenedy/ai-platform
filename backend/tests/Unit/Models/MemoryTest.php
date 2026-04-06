<?php

declare(strict_types=1);
use App\Models\Memory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pgvector\Laravel\HasNeighbors;

it('has correct fillable attributes', function (): void {
    $memory = new Memory();
    expect($memory->getFillable())->toContain(
        'user_id',
        'content',
        'source_conversation_id',
        'source_message_id',
        'category',
        'importance',
        'is_active',
        'embedding',
    );
});

it('has correct hidden attributes', function (): void {
    $memory = new Memory();
    expect($memory->getHidden())->toContain('embedding');
});

it('has correct casts', function (): void {
    $memory = new Memory();
    $casts = $memory->getCasts();
    expect($casts)
        ->toHaveKey('importance', 'integer')
        ->toHaveKey('is_active', 'boolean');
});

it('uses ulids', function (): void {
    expect(in_array(HasUlids::class, class_uses_recursive(Memory::class), true))->toBeTrue();
});

it('uses soft deletes', function (): void {
    expect(in_array(SoftDeletes::class, class_uses_recursive(Memory::class), true))->toBeTrue();
});

it('uses has neighbors', function (): void {
    expect(in_array(HasNeighbors::class, class_uses_recursive(Memory::class), true))->toBeTrue();
});
