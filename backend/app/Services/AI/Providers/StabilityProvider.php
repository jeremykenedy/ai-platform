<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AbstractAiProvider;
use Illuminate\Support\Facades\Http;

class StabilityProvider extends AbstractAiProvider
{
    protected string $apiKey;

    public function __construct()
    {
        parent::__construct('https://api.stability.ai');

        $this->apiKey = (string) config('services.stability.api_key', '');

        $this->capabilities = ['image_generation'];
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     *
     * @return array{content: string, tokens_used: int, finish_reason: string}
     */
    public function chat(array $messages, string $model, array $options = []): array
    {
        throw new \RuntimeException('StabilityProvider does not support chat completions.');
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     */
    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        throw new \RuntimeException('StabilityProvider does not support streaming.');
    }

    /**
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array
    {
        throw new \RuntimeException('StabilityProvider does not support embeddings.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        return [
            ['id' => 'core', 'endpoint' => '/v2beta/stable-image/generate/core', 'type' => 'image_generation'],
            ['id' => 'ultra', 'endpoint' => '/v2beta/stable-image/generate/ultra', 'type' => 'image_generation'],
            ['id' => 'sd3-large', 'endpoint' => '/v2beta/stable-image/generate/sd3', 'type' => 'image_generation'],
            ['id' => 'sd3-large-turbo', 'endpoint' => '/v2beta/stable-image/generate/sd3', 'type' => 'image_generation'],
        ];
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Generate an image and return the raw base64-encoded image data.
     *
     * @param array<string, mixed> $options
     */
    public function generateImage(string $prompt, array $options = []): string
    {
        $formData = array_merge([
            'prompt'        => $prompt,
            'output_format' => 'webp',
        ], $options);

        $request = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Accept'        => 'application/json',
        ])
            ->timeout(120)
            ->connectTimeout(10)
            ->asMultipart();

        $multipart = [];

        foreach ($formData as $name => $value) {
            $multipart[] = ['name' => $name, 'contents' => (string) $value];
        }

        $response = $request->post(
            "{$this->baseUrl}/v2beta/stable-image/generate/core",
            $multipart,
        );

        $response->throw();

        $data = $response->json();

        if (isset($data['image'])) {
            return $data['image'];
        }

        throw new \RuntimeException('Stability AI response did not contain an image.');
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
        ];
    }
}
