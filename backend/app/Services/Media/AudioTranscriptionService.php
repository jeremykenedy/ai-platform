<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Services\AI\ModelRouterService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AudioTranscriptionService
{
    public function __construct(
        private readonly ModelRouterService $modelRouter,
    ) {}

    /**
     * Transcribe an audio file to text using the best available provider.
     *
     * @param  array{
     *     model?: string,
     *     language?: string,
     *     response_format?: string,
     * }  $options
     * @return array{text: string, language: string|null, duration: float|null, provider: string}
     */
    public function transcribe(string $audioPath, array $options = []): array
    {
        $provider = $this->resolveProvider();

        if ($provider === null) {
            throw new \RuntimeException('No audio transcription providers are available');
        }

        return match ($provider) {
            'openai' => $this->transcribeViaOpenAi($audioPath, $options),
            'groq' => $this->transcribeViaGroq($audioPath, $options),
            'deepgram' => $this->transcribeViaDeepgram($audioPath, $options),
            default => throw new \RuntimeException("Unknown transcription provider: {$provider}"),
        };
    }

    /**
     * Return a map of provider names to availability status.
     *
     * @return array<string, bool>
     */
    public function getAvailableProviders(): array
    {
        return [
            'openai' => $this->isOpenAiConfigured(),
            'groq' => $this->isGroqConfigured(),
            'deepgram' => $this->isDeepgramConfigured(),
        ];
    }

    /**
     * Transcribe via OpenAI Whisper.
     *
     * @param  array<string, mixed>  $options
     * @return array{text: string, language: string|null, duration: float|null, provider: string}
     */
    private function transcribeViaOpenAi(string $audioPath, array $options): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        $model = $options['model'] ?? 'whisper-1';
        $responseFormat = $options['response_format'] ?? 'verbose_json';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->connectTimeout(10)
                ->attach('file', (string) file_get_contents($audioPath), basename($audioPath))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => $model,
                    'response_format' => $responseFormat,
                    'language' => $options['language'] ?? null,
                ]);

            $response->throw();

            $data = $response->json();

            return [
                'text' => $data['text'] ?? '',
                'language' => $data['language'] ?? null,
                'duration' => isset($data['duration']) ? (float) $data['duration'] : null,
                'provider' => 'openai',
            ];
        } catch (\Throwable $e) {
            Log::error('[AudioTranscriptionService] OpenAI transcription failed', [
                'file' => $audioPath,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Transcribe via Groq Whisper (same API format as OpenAI, different base URL).
     *
     * @param  array<string, mixed>  $options
     * @return array{text: string, language: string|null, duration: float|null, provider: string}
     */
    private function transcribeViaGroq(string $audioPath, array $options): array
    {
        $apiKey = (string) config('services.groq.api_key', '');
        $model = $options['model'] ?? 'whisper-large-v3';
        $responseFormat = $options['response_format'] ?? 'verbose_json';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->connectTimeout(10)
                ->attach('file', (string) file_get_contents($audioPath), basename($audioPath))
                ->post('https://api.groq.com/openai/v1/audio/transcriptions', [
                    'model' => $model,
                    'response_format' => $responseFormat,
                    'language' => $options['language'] ?? null,
                ]);

            $response->throw();

            $data = $response->json();

            return [
                'text' => $data['text'] ?? '',
                'language' => $data['language'] ?? null,
                'duration' => isset($data['duration']) ? (float) $data['duration'] : null,
                'provider' => 'groq',
            ];
        } catch (\Throwable $e) {
            Log::error('[AudioTranscriptionService] Groq transcription failed', [
                'file' => $audioPath,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Transcribe via Deepgram Nova.
     *
     * @param  array<string, mixed>  $options
     * @return array{text: string, language: string|null, duration: float|null, provider: string}
     */
    private function transcribeViaDeepgram(string $audioPath, array $options): array
    {
        $apiKey = (string) config('services.deepgram.api_key', '');
        $model = $options['model'] ?? (string) config('services.deepgram.default_model', 'nova-3');

        $audioData = file_get_contents($audioPath);

        if ($audioData === false) {
            throw new \RuntimeException("Failed to read audio file: {$audioPath}");
        }

        $mimeType = $this->detectAudioMimeType($audioPath);

        $queryParams = [
            'model' => $model,
            'smart_format' => 'true',
            'punctuate' => 'true',
        ];

        if (isset($options['language'])) {
            $queryParams['language'] = $options['language'];
        }

        $url = 'https://api.deepgram.com/v1/listen?'.http_build_query($queryParams);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Token {$apiKey}",
                'Content-Type' => $mimeType,
            ])
                ->withBody($audioData, $mimeType)
                ->timeout(120)
                ->connectTimeout(10)
                ->post($url);

            $response->throw();

            $data = $response->json();
            $results = $data['results'] ?? [];
            $channels = $results['channels'] ?? [];
            $alternatives = $channels[0]['alternatives'] ?? [];
            $transcript = $alternatives[0]['transcript'] ?? '';
            $detectedLanguage = $results['channels'][0]['detected_language'] ?? null;
            $duration = $data['metadata']['duration'] ?? null;

            return [
                'text' => $transcript,
                'language' => $detectedLanguage,
                'duration' => $duration !== null ? (float) $duration : null,
                'provider' => 'deepgram',
            ];
        } catch (\Throwable $e) {
            Log::error('[AudioTranscriptionService] Deepgram transcription failed', [
                'file' => $audioPath,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Resolve the first available provider in priority order.
     */
    private function resolveProvider(): ?string
    {
        foreach ($this->getAvailableProviders() as $name => $available) {
            if ($available) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Detect MIME type for an audio file based on extension.
     */
    private function detectAudioMimeType(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            'webm' => 'audio/webm',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            default => 'audio/mpeg',
        };
    }

    private function isOpenAiConfigured(): bool
    {
        $key = (string) config('services.openai.api_key', '');

        return $key !== '';
    }

    private function isGroqConfigured(): bool
    {
        $key = (string) config('services.groq.api_key', '');

        return $key !== '';
    }

    private function isDeepgramConfigured(): bool
    {
        $key = (string) config('services.deepgram.api_key', '');

        return $key !== '';
    }
}
