<?php

declare(strict_types=1);
use App\Models\Persona;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

it('has correct fillable attributes', function (): void {
    $persona = new Persona;
    expect($persona->getFillable())->toContain(
        'user_id',
        'name',
        'description',
        'system_prompt',
        'model_name',
        'temperature',
        'top_p',
        'top_k',
        'repeat_penalty',
    );
});

it('has correct casts', function (): void {
    $persona = new Persona;
    $casts = $persona->getCasts();
    expect($casts)
        ->toHaveKey('temperature', 'float')
        ->toHaveKey('top_p', 'float');
});

it('uses ulids', function (): void {
    expect(in_array(HasUlids::class, class_uses_recursive(Persona::class), true))->toBeTrue();
});

it('uses soft deletes', function (): void {
    expect(in_array(SoftDeletes::class, class_uses_recursive(Persona::class), true))->toBeTrue();
});
