<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AbstractAiProvider;
use Illuminate\Support\Facades\Http;

class DeepgramProvider extends AbstractAiProvider
{
    protected string $apiKey;

    public function __construct()
    {
        parent::__construct('https://api.deepgram.com');

        $this->apiKey = (string) config('services.deepgram.api_key', '');

        $this->capabilities = ['audio_transcription'];
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     *
     * @return array{content: string, tokens_used: int, finish_reason: string}
     */
    public function chat(array $messages, string $model, array $options = []): array
    {
        throw new \RuntimeException('DeepgramProvider does not support chat completions.');
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     */
    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        throw new \RuntimeException('DeepgramProvider does not support chat streaming.');
    }

    /**
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array
    {
        throw new \RuntimeException('DeepgramProvider does not support embeddings.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        return [
            ['id' => 'nova-3', 'description' => 'Deepgram Nova-3 (latest, most accurate)'],
            ['id' => 'nova-2', 'description' => 'Deepgram Nova-2'],
            ['id' => 'nova', 'description' => 'Deepgram Nova'],
            ['id' => 'enhanced', 'description' => 'Deepgram Enhanced'],
            ['id' => 'base', 'description' => 'Deepgram Base'],
            ['id' => 'whisper-large', 'description' => 'Whisper Large (via Deepgram)'],
            ['id' => 'whisper-medium', 'description' => 'Whisper Medium (via Deepgram)'],
            ['id' => 'whisper-small', 'description' => 'Whisper Small (via Deepgram)'],
            ['id' => 'whisper-base', 'description' => 'Whisper Base (via Deepgram)'],
            ['id' => 'whisper-tiny', 'description' => 'Whisper Tiny (via Deepgram)'],
        ];
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Transcribe audio and return the transcript text with metadata.
     *
     * @param array<string, mixed> $options Deepgram query parameters (punctuate, diarize, language, etc.)
     *
     * @return array{transcript: string, confidence: float, words: array<int, array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function transcribe(string $audioData, string $model = 'nova-3', array $options = []): array
    {
        $queryParams = array_merge([
            'model'        => $model,
            'punctuate'    => 'true',
            'smart_format' => 'true',
        ], $options);

        $url = "{$this->baseUrl}/v1/listen?".http_build_query($queryParams);

        $response = Http::withHeaders([
            'Authorization' => "Token {$this->apiKey}",
            'Content-Type'  => 'audio/*',
        ])
            ->timeout(120)
            ->connectTimeout(10)
            ->withBody($audioData, 'audio/*')
            ->post($url);

        $response->throw();

        $data = $response->json();

        $channel = $data['results']['channels'][0] ?? [];
        $alternative = $channel['alternatives'][0] ?? [];

        return [
            'transcript' => $alternative['transcript'] ?? '',
            'confidence' => (float) ($alternative['confidence'] ?? 0.0),
            'words'      => $alternative['words'] ?? [],
            'metadata'   => $data['metadata'] ?? [],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        return [
            'Authorization' => "Token {$this->apiKey}",
            'Content-Type'  => 'application/json',
        ];
    }
}
