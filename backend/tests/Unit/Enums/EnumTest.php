<?php

declare(strict_types=1);
use App\Enums\AuthType;
use App\Enums\BenchmarkCategory;
use App\Enums\Capability;
use App\Enums\ConflictResolution;
use App\Enums\DatasetFormat;
use App\Enums\ExtractionStatus;
use App\Enums\FinishReason;
use App\Enums\HealthStatus;
use App\Enums\IntegrationCategory;
use App\Enums\MemoryCategory;
use App\Enums\MessageRole;
use App\Enums\ProviderType;
use App\Enums\Theme;
use App\Enums\ToolCallStatus;
use App\Enums\TrainingJobStatus;

it('MessageRole has correct cases', function (): void {
    expect(MessageRole::cases())->toHaveCount(3);
    expect(MessageRole::User->value)->toBe('user');
    expect(MessageRole::Assistant->value)->toBe('assistant');
    expect(MessageRole::System->value)->toBe('system');
});

it('TrainingJobStatus has correct cases', function (): void {
    expect(TrainingJobStatus::cases())->toHaveCount(5);
});

it('Capability has correct cases', function (): void {
    expect(Capability::cases())->toHaveCount(14);
    expect(Capability::Chat->value)->toBe('chat');
});

it('MemoryCategory has correct cases', function (): void {
    expect(MemoryCategory::cases())->toHaveCount(5);
});

it('Theme has correct cases', function (): void {
    expect(Theme::cases())->toHaveCount(3);
});

it('ProviderType has correct cases', function (): void {
    expect(ProviderType::cases())->toHaveCount(2);
});

it('HealthStatus has correct cases', function (): void {
    expect(HealthStatus::cases())->toHaveCount(3);
});

it('FinishReason has correct cases', function (): void {
    expect(FinishReason::cases())->toHaveCount(4);
});

it('AuthType has correct cases', function (): void {
    expect(AuthType::cases())->toHaveCount(4);
});

it('ExtractionStatus has correct cases', function (): void {
    expect(ExtractionStatus::cases())->toHaveCount(3);
});

it('DatasetFormat has correct cases', function (): void {
    expect(DatasetFormat::cases())->toHaveCount(2);
});

it('ConflictResolution has correct cases', function (): void {
    expect(ConflictResolution::cases())->toHaveCount(4);
});

it('IntegrationCategory has correct cases', function (): void {
    expect(IntegrationCategory::cases())->toHaveCount(10);
});

it('ToolCallStatus has correct cases', function (): void {
    expect(ToolCallStatus::cases())->toHaveCount(3);
});

it('BenchmarkCategory has correct cases', function (): void {
    expect(BenchmarkCategory::cases())->toHaveCount(5);
});
