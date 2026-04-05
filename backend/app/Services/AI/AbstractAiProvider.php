<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractAiProvider implements AiProviderInterface
{
    protected Http $http;

    protected string $baseUrl;

    /** @var string[] */
    protected array $capabilities = [];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supportsCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * @return array{success: bool, latency_ms: int, error: string|null}
     */
    public function testConnection(): array
    {
        $start = hrtime(true);

        try {
            $this->chat(
                [['role' => 'user', 'content' => 'Say OK']],
                $this->getDefaultTestModel(),
                ['max_tokens' => 5],
            );

            $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

            return [
                'success' => true,
                'latency_ms' => $latencyMs,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

            Log::warning(sprintf('[%s] testConnection failed: %s', static::class, $e->getMessage()));

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function makeRequest(string $method, string $url, array $data = []): array
    {
        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(30)
            ->connectTimeout(10)
            ->{$method}($url, $data);

        $response->throw();

        return $response->json() ?? [];
    }

    protected function getDefaultTestModel(): string
    {
        return '';
    }
}
