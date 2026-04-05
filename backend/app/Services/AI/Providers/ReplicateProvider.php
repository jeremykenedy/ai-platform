<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AbstractAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReplicateProvider extends AbstractAiProvider
{
    protected string $apiToken;

    public function __construct()
    {
        parent::__construct('https://api.replicate.com');

        $this->apiToken = (string) config('services.replicate.api_token', '');

        $this->capabilities = ['image_generation'];
    }

    /**
     * Replicate is image-only; chat is not supported.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{content: string, tokens_used: int, finish_reason: string}
     */
    public function chat(array $messages, string $model, array $options = []): array
    {
        throw new \RuntimeException('ReplicateProvider does not support chat completions.');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        throw new \RuntimeException('ReplicateProvider does not support streaming.');
    }

    /**
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array
    {
        throw new \RuntimeException('ReplicateProvider does not support embeddings.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        return [
            ['id' => 'black-forest-labs/flux-1.1-pro', 'type' => 'image_generation'],
            ['id' => 'black-forest-labs/flux-schnell', 'type' => 'image_generation'],
            ['id' => 'black-forest-labs/flux-dev', 'type' => 'image_generation'],
            ['id' => 'stability-ai/sdxl', 'type' => 'image_generation'],
            ['id' => 'stability-ai/stable-diffusion-3', 'type' => 'image_generation'],
            ['id' => 'lucataco/flux-dev-lora', 'type' => 'image_generation'],
            ['id' => 'bytedance/sdxl-lightning-4step', 'type' => 'image_generation'],
        ];
    }

    public function isAvailable(): bool
    {
        return $this->apiToken !== '';
    }

    /**
     * Submit a prediction to Replicate and poll until it completes.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function generateImage(string $model, array $input): array
    {
        $createResponse = Http::withHeaders($this->buildHeaders())
            ->timeout(30)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/v1/predictions", [
                'version' => $this->resolveModelVersion($model),
                'input' => $input,
            ]);

        $createResponse->throw();

        $prediction = $createResponse->json();
        $predictionId = $prediction['id'] ?? '';

        if ($predictionId === '') {
            throw new \RuntimeException('Replicate prediction ID missing from response.');
        }

        return $this->pollPrediction($predictionId);
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        return [
            'Authorization' => "Token {$this->apiToken}",
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Poll a prediction until it reaches a terminal state.
     *
     * @return array<string, mixed>
     */
    private function pollPrediction(string $predictionId): array
    {
        $maxAttempts = 60;
        $attempt = 0;
        $sleepSeconds = 2;

        while ($attempt < $maxAttempts) {
            $response = Http::withHeaders($this->buildHeaders())
                ->timeout(30)
                ->connectTimeout(10)
                ->get("{$this->baseUrl}/v1/predictions/{$predictionId}");

            $response->throw();

            $data = $response->json();
            $status = $data['status'] ?? '';

            if ($status === 'succeeded') {
                return $data;
            }

            if ($status === 'failed' || $status === 'canceled') {
                $error = $data['error'] ?? 'Prediction failed with status: '.$status;
                throw new \RuntimeException($error);
            }

            sleep($sleepSeconds);
            $attempt++;
        }

        throw new \RuntimeException('Replicate prediction timed out after '.($maxAttempts * $sleepSeconds).' seconds.');
    }

    /**
     * Resolve a model slug to its latest version ID if needed.
     */
    private function resolveModelVersion(string $model): string
    {
        // If the model already contains a version hash (colon-separated), use it directly.
        if (str_contains($model, ':')) {
            return explode(':', $model, 2)[1];
        }

        try {
            [$owner, $name] = explode('/', $model, 2);

            $response = Http::withHeaders($this->buildHeaders())
                ->timeout(15)
                ->connectTimeout(10)
                ->get("{$this->baseUrl}/v1/models/{$owner}/{$name}");

            $response->throw();

            return $response->json()['latest_version']['id'] ?? $model;
        } catch (\Throwable $e) {
            Log::warning('[ReplicateProvider] resolveModelVersion failed: '.$e->getMessage());

            return $model;
        }
    }
}
