<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('returns health status', function (): void {
    Http::fake();

    $response = $this->getJson('/api/health');

    $response->assertJsonStructure(['status', 'services']);
    expect($response->status())->toBeIn([200, 503]);
});

it('health response includes expected service keys', function (): void {
    Http::fake();

    $response = $this->getJson('/api/health');

    $response->assertJsonStructure([
        'status',
        'services' => ['database', 'redis', 'ollama', 'minio'],
        'timestamp',
    ]);
});
