<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Services\AI\ModelRouterService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TextToSpeechService
{
    public function __construct(
        private readonly ModelRouterService $modelRouter,
    ) {}

    /**
     * Synthesize speech from text using the best available provider.
     *
     * @param  array{
     *     voice?: string,
     *     model?: string,
     *     speed?: float,
     *     format?: string,
     * }  $options
     * @return array{audio_data: string, format: string, provider: string, duration: float|null}
     */
    public function synthesize(string $text, array $options = []): array
    {
        $provider = $this->resolveProvider();

        if ($provider === null) {
            throw new \RuntimeException('No text-to-speech providers are available');
        }

        return match ($provider) {
            'elevenlabs' => $this->synthesizeViaElevenLabs($text, $options),
            'openai' => $this->synthesizeViaOpenAi($text, $options),
            default => throw new \RuntimeException("Unknown TTS provider: {$provider}"),
        };
    }

    /**
     * List available voices from all configured providers.
     *
     * @return array<string, array<int, array{id: string, name: string, provider: string, language: string|null, preview_url: string|null}>>
     */
    public function getAvailableVoices(): array
    {
        $voices = [];

        if ($this->isElevenLabsConfigured()) {
            try {
                $voices['elevenlabs'] = $this->listElevenLabsVoices();
            } catch (\Throwable $e) {
                Log::warning('[TextToSpeechService] Failed to list ElevenLabs voices: '.$e->getMessage());
                $voices['elevenlabs'] = [];
            }
        }

        if ($this->isOpenAiConfigured()) {
            $voices['openai'] = $this->listOpenAiVoices();
        }

        return $voices;
    }

    /**
     * Return a map of provider names to availability status.
     *
     * @return array<string, bool>
     */
    public function getAvailableProviders(): array
    {
        return [
            'elevenlabs' => $this->isElevenLabsConfigured(),
            'openai' => $this->isOpenAiConfigured(),
        ];
    }

    /**
     * Synthesize speech via ElevenLabs.
     *
     * @param  array<string, mixed>  $options
     * @return array{audio_data: string, format: string, provider: string, duration: float|null}
     */
    private function synthesizeViaElevenLabs(string $text, array $options): array
    {
        $apiKey = (string) config('services.elevenlabs.api_key', '');
        $voiceId = $options['voice'] ?? (string) config('services.elevenlabs.default_voice', 'pNInz6obpgDQGcFmaJgB');
        $modelId = $options['model'] ?? (string) config('services.elevenlabs.default_model', 'eleven_multilingual_v2');
        $format = $options['format'] ?? 'mp3';
        $speed = isset($options['speed']) ? (float) $options['speed'] : 1.0;

        $outputFormat = match ($format) {
            'mp3' => 'mp3_44100_128',
            'wav' => 'pcm_44100',
            'ogg' => 'ulaw_8000',
            default => 'mp3_44100_128',
        };

        $payload = [
            'text' => $text,
            'model_id' => $modelId,
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.75,
                'speed' => max(0.25, min(4.0, $speed)),
            ],
        ];

        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Accept' => 'audio/'.($format === 'wav' ? 'wav' : 'mpeg'),
            ])
                ->timeout(60)
                ->connectTimeout(10)
                ->post(
                    "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}?output_format={$outputFormat}",
                    $payload,
                );

            $response->throw();

            $audioData = $response->body();
            $duration = $this->estimateAudioDuration($audioData, $format);

            return [
                'audio_data' => $audioData,
                'format' => $format,
                'provider' => 'elevenlabs',
                'duration' => $duration,
            ];
        } catch (\Throwable $e) {
            Log::error('[TextToSpeechService] ElevenLabs synthesis failed', [
                'voice_id' => $voiceId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Synthesize speech via OpenAI TTS.
     *
     * @param  array<string, mixed>  $options
     * @return array{audio_data: string, format: string, provider: string, duration: float|null}
     */
    private function synthesizeViaOpenAi(string $text, array $options): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        $voice = $options['voice'] ?? (string) config('services.openai.tts_default_voice', 'alloy');
        $model = $options['model'] ?? (string) config('services.openai.tts_model', 'tts-1');
        $format = $options['format'] ?? 'mp3';
        $speed = isset($options['speed']) ? (float) $options['speed'] : 1.0;

        $validVoices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];

        if (! in_array($voice, $validVoices, true)) {
            $voice = 'alloy';
        }

        $validFormats = ['mp3', 'opus', 'aac', 'flac', 'wav', 'pcm'];

        if (! in_array($format, $validFormats, true)) {
            $format = 'mp3';
        }

        $payload = [
            'model' => $model,
            'input' => $text,
            'voice' => $voice,
            'response_format' => $format,
            'speed' => max(0.25, min(4.0, $speed)),
        ];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->connectTimeout(10)
                ->post('https://api.openai.com/v1/audio/speech', $payload);

            $response->throw();

            $audioData = $response->body();
            $duration = $this->estimateAudioDuration($audioData, $format);

            return [
                'audio_data' => $audioData,
                'format' => $format,
                'provider' => 'openai',
                'duration' => $duration,
            ];
        } catch (\Throwable $e) {
            Log::error('[TextToSpeechService] OpenAI TTS synthesis failed', [
                'voice' => $voice,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Fetch the list of available voices from ElevenLabs.
     *
     * @return array<int, array{id: string, name: string, provider: string, language: string|null, preview_url: string|null}>
     */
    private function listElevenLabsVoices(): array
    {
        $apiKey = (string) config('services.elevenlabs.api_key', '');

        $response = Http::withHeaders(['xi-api-key' => $apiKey])
            ->timeout(15)
            ->connectTimeout(5)
            ->get('https://api.elevenlabs.io/v1/voices');

        $response->throw();

        $voices = $response->json('voices') ?? [];

        return array_map(fn (array $voice): array => [
            'id' => $voice['voice_id'] ?? '',
            'name' => $voice['name'] ?? '',
            'provider' => 'elevenlabs',
            'language' => $voice['labels']['language'] ?? null,
            'preview_url' => $voice['preview_url'] ?? null,
        ], $voices);
    }

    /**
     * Return the static list of OpenAI TTS voices.
     *
     * @return array<int, array{id: string, name: string, provider: string, language: string|null, preview_url: string|null}>
     */
    private function listOpenAiVoices(): array
    {
        $voiceNames = [
            'alloy' => 'Alloy',
            'echo' => 'Echo',
            'fable' => 'Fable',
            'onyx' => 'Onyx',
            'nova' => 'Nova',
            'shimmer' => 'Shimmer',
        ];

        return array_map(
            fn (string $id, string $name): array => [
                'id' => $id,
                'name' => $name,
                'provider' => 'openai',
                'language' => null,
                'preview_url' => null,
            ],
            array_keys($voiceNames),
            array_values($voiceNames),
        );
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
     * Estimate audio duration in seconds from binary audio data.
     * This is a rough heuristic based on file size and average bitrate.
     */
    private function estimateAudioDuration(string $audioData, string $format): ?float
    {
        $bytes = strlen($audioData);

        if ($bytes === 0) {
            return null;
        }

        // Approximate bitrates in kbps.
        $bitrates = [
            'mp3' => 128,
            'opus' => 64,
            'aac' => 128,
            'flac' => 800,
            'wav' => 1411,
            'pcm' => 1411,
            'ogg' => 96,
        ];

        $kbps = $bitrates[$format] ?? 128;

        return round(($bytes * 8) / ($kbps * 1000), 2);
    }

    private function isElevenLabsConfigured(): bool
    {
        $key = (string) config('services.elevenlabs.api_key', '');

        return $key !== '';
    }

    private function isOpenAiConfigured(): bool
    {
        $key = (string) config('services.openai.api_key', '');

        return $key !== '';
    }
}
