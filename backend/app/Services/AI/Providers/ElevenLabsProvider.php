<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AbstractAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ElevenLabsProvider extends AbstractAiProvider
{
    protected string $apiKey;

    public function __construct()
    {
        parent::__construct('https://api.elevenlabs.io');

        $this->apiKey = (string) config('services.elevenlabs.api_key', '');

        $this->capabilities = ['audio_generation'];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{content: string, tokens_used: int, finish_reason: string}
     */
    public function chat(array $messages, string $model, array $options = []): array
    {
        throw new \RuntimeException('ElevenLabsProvider does not support chat completions.');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        throw new \RuntimeException('ElevenLabsProvider does not support chat streaming.');
    }

    /**
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array
    {
        throw new \RuntimeException('ElevenLabsProvider does not support embeddings.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        try {
            $response = Http::withHeaders($this->buildHeaders())
                ->timeout(30)
                ->connectTimeout(10)
                ->get("{$this->baseUrl}/v1/models");

            $response->throw();

            return array_map(fn (array $m): array => [
                'id' => $m['model_id'],
                'name' => $m['name'] ?? $m['model_id'],
                'description' => $m['description'] ?? null,
                'languages' => $m['languages'] ?? [],
            ], $response->json() ?? []);
        } catch (\Throwable $e) {
            Log::warning('[ElevenLabsProvider] listModels failed: '.$e->getMessage());

            return [];
        }
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Convert text to speech and return raw audio bytes.
     */
    public function textToSpeech(string $text, string $voiceId, string $modelId = 'eleven_turbo_v2_5'): string
    {
        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'audio/mpeg',
        ])
            ->timeout(120)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/v1/text-to-speech/{$voiceId}", [
                'text' => $text,
                'model_id' => $modelId,
                'voice_settings' => [
                    'stability' => 0.5,
                    'similarity_boost' => 0.75,
                ],
            ]);

        $response->throw();

        return $response->body();
    }

    /**
     * List all available ElevenLabs voices.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listVoices(): array
    {
        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(30)
            ->connectTimeout(10)
            ->get("{$this->baseUrl}/v1/voices");

        $response->throw();

        return array_map(fn (array $v): array => [
            'voice_id' => $v['voice_id'],
            'name' => $v['name'],
            'category' => $v['category'] ?? null,
            'labels' => $v['labels'] ?? [],
            'preview_url' => $v['preview_url'] ?? null,
        ], $response->json()['voices'] ?? []);
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        return [
            'xi-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }
}
