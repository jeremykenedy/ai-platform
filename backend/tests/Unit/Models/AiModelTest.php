<?php

declare(strict_types=1);
use App\Models\AiModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;

it('has correct fillable attributes', function (): void {
    $model = new AiModel();
    expect($model->getFillable())->toContain(
        'provider_id',
        'name',
        'display_name',
        'capabilities',
        'is_active',
        'is_default',
        'is_local',
    );
});

it('has correct casts', function (): void {
    $model = new AiModel();
    $casts = $model->getCasts();
    expect($casts)
        ->toHaveKey('capabilities', 'array')
        ->toHaveKey('is_active', 'boolean')
        ->toHaveKey('supports_vision', 'boolean');
});

it('uses ulids', function (): void {
    expect(in_array(HasUlids::class, class_uses_recursive(AiModel::class), true))->toBeTrue();
});

it('uses soft deletes', function (): void {
    expect(in_array(SoftDeletes::class, class_uses_recursive(AiModel::class), true))->toBeTrue();
});
