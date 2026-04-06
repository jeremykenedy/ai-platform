<?php

declare(strict_types=1);
use App\Models\Message;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pgvector\Laravel\HasNeighbors;

it('has correct fillable attributes', function (): void {
    $message = new Message();
    expect($message->getFillable())->toContain(
        'conversation_id',
        'role',
        'content',
        'tokens_used',
        'finish_reason',
        'model_version',
        'sequence',
        'embedding',
    );
});

it('has correct hidden attributes', function (): void {
    $message = new Message();
    expect($message->getHidden())->toContain('embedding');
});

it('uses ulids', function (): void {
    expect(in_array(HasUlids::class, class_uses_recursive(Message::class), true))->toBeTrue();
});

it('uses soft deletes', function (): void {
    expect(in_array(SoftDeletes::class, class_uses_recursive(Message::class), true))->toBeTrue();
});

it('uses has neighbors', function (): void {
    expect(in_array(HasNeighbors::class, class_uses_recursive(Message::class), true))->toBeTrue();
});
