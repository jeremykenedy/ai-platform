<?php

declare(strict_types=1);
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

it('has correct fillable attributes', function (): void {
    $provider = new AiProvider();
    expect($provider->getFillable())->toContain(
        'name',
        'display_name',
        'type',
        'base_url',
        'is_active',
        'is_configured',
        'capabilities',
        'config',
    );
});

it('has correct casts', function (): void {
    $provider = new AiProvider();
    $casts = $provider->getCasts();
    expect($casts)
        ->toHaveKey('capabilities', 'array')
        ->toHaveKey('config', 'array')
        ->toHaveKey('is_active', 'boolean');
});

it('uses ulids', function (): void {
    expect(in_array(HasUlids::class, class_uses_recursive(AiProvider::class), true))->toBeTrue();
});
