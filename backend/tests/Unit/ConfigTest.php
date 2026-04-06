<?php

declare(strict_types=1);

it('has ai config with default model', function (): void {
    expect(config('ai.default_local_model'))->toBe('llama3.2:latest');
    expect(config('ai.default_embedding_model'))->toBe('nomic-embed-text:latest');
    expect(config('ai.auto_routing'))->toBeTrue();
    expect(config('ai.prefer_local'))->toBeTrue();
});

it('has services config for ollama', function (): void {
    expect(config('services.ollama'))->toBeArray()->toHaveKey('base_url');
});

it('has services config for all providers', function (): void {
    $providers = ['anthropic', 'openai', 'google', 'mistral', 'groq', 'together', 'openrouter', 'replicate', 'stability', 'elevenlabs', 'deepgram', 'comfyui'];
    foreach ($providers as $provider) {
        expect(config("services.{$provider}"))->toBeArray("Missing services config for {$provider}");
    }
});

it('has horizon config with correct environments', function (): void {
    expect(config('horizon.environments.production'))->toBeArray()->toHaveKey('supervisor-default');
    expect(config('horizon.environments.local'))->toBeArray()->toHaveKey('supervisor-default');
});

it('has app timezone configured', function (): void {
    expect(config('app.timezone'))->toBe('America/Los_Angeles');
});

it('has super admin config', function (): void {
    expect(config('app.super_admin'))->toBeArray()->toHaveKeys(['name', 'email', 'password']);
});
