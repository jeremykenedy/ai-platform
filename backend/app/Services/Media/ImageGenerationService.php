<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Services\AI\ModelRouterService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageGenerationService
{
    public function __construct(
        private readonly ModelRouterService $modelRouter,
    ) {}

    /**
     * Generate an image from a text prompt using the best available provider.
     *
     * @param  array{
     *     negative_prompt?: string,
     *     model?: string,
     *     aspect_ratio?: string,
     *     quality?: string,
     *     style?: string,
     * }  $options
     * @return array{image_data: string, provider: string, model: string, metadata: array<string, mixed>}
     */
    public function generate(string $prompt, array $options = []): array
    {
        $provider = $this->getDefaultProvider();

        if ($provider === null) {
            throw new \RuntimeException('No image generation providers are available');
        }

        return match ($provider) {
            'comfyui' => $this->generateViaComfyUi($prompt, $options),
            'replicate' => $this->generateViaReplicate($prompt, $options),
            'stability' => $this->generateViaStability($prompt, $options),
            'openai' => $this->generateViaOpenAi($prompt, $options),
            default => throw new \RuntimeException("Unknown provider: {$provider}"),
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
            'comfyui' => $this->isComfyUiAvailable(),
            'replicate' => $this->isReplicateConfigured(),
            'stability' => $this->isStabilityConfigured(),
            'openai' => $this->isOpenAiConfigured(),
        ];
    }

    /**
     * Return the name of the first available provider, or null if none are available.
     */
    public function getDefaultProvider(): ?string
    {
        foreach ($this->getAvailableProviders() as $name => $available) {
            if ($available) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Generate an image via a locally running ComfyUI instance.
     *
     * @param  array<string, mixed>  $options
     * @return array{image_data: string, provider: string, model: string, metadata: array<string, mixed>}
     */
    private function generateViaComfyUi(string $prompt, array $options): array
    {
        $baseUrl = rtrim((string) config('services.comfyui.base_url', 'http://comfyui:8188'), '/');
        $dimensions = $this->mapAspectRatio($options['aspect_ratio'] ?? '1:1');
        $negativePrompt = $options['negative_prompt'] ?? '';
        $model = $options['model'] ?? (string) config('services.comfyui.default_model', 'v1-5-pruned-emaonly.ckpt');

        // Minimal txt2img workflow for ComfyUI API.
        $workflow = [
            '3' => [
                'inputs' => [
                    'seed' => random_int(0, 2147483647),
                    'steps' => $options['quality'] === 'ultra' ? 40 : ($options['quality'] === 'high' ? 30 : 20),
                    'cfg' => 7.0,
                    'sampler_name' => 'euler',
                    'scheduler' => 'normal',
                    'denoise' => 1.0,
                    'model' => ['4', 0],
                    'positive' => ['6', 0],
                    'negative' => ['7', 0],
                    'latent_image' => ['5', 0],
                ],
                'class_type' => 'KSampler',
            ],
            '4' => [
                'inputs' => ['ckpt_name' => $model],
                'class_type' => 'CheckpointLoaderSimple',
            ],
            '5' => [
                'inputs' => [
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'batch_size' => 1,
                ],
                'class_type' => 'EmptyLatentImage',
            ],
            '6' => [
                'inputs' => ['text' => $prompt, 'clip' => ['4', 1]],
                'class_type' => 'CLIPTextEncode',
            ],
            '7' => [
                'inputs' => ['text' => $negativePrompt, 'clip' => ['4', 1]],
                'class_type' => 'CLIPTextEncode',
            ],
            '8' => [
                'inputs' => ['samples' => ['3', 0], 'vae' => ['4', 2]],
                'class_type' => 'VAEDecode',
            ],
            '9' => [
                'inputs' => [
                    'filename_prefix' => 'ai_platform_',
                    'images' => ['8', 0],
                ],
                'class_type' => 'SaveImage',
            ],
        ];

        $queueResponse = Http::timeout(10)
            ->connectTimeout(5)
            ->post("{$baseUrl}/prompt", ['prompt' => $workflow]);

        $queueResponse->throw();

        $promptId = $queueResponse->json('prompt_id');

        if (! $promptId) {
            throw new \RuntimeException('ComfyUI did not return a prompt_id');
        }

        // Poll for completion.
        $imageData = $this->pollComfyUiResult($baseUrl, (string) $promptId, (string) $options['node_id'] ?? '9');

        return [
            'image_data' => $imageData,
            'provider' => 'comfyui',
            'model' => $model,
            'metadata' => [
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'aspect_ratio' => $options['aspect_ratio'] ?? '1:1',
                'prompt_id' => $promptId,
            ],
        ];
    }

    /**
     * Poll ComfyUI history endpoint until the image is ready.
     */
    private function pollComfyUiResult(string $baseUrl, string $promptId, string $outputNodeId): string
    {
        $maxAttempts = 60;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(2);
            $attempt++;

            try {
                $historyResponse = Http::timeout(10)
                    ->connectTimeout(5)
                    ->get("{$baseUrl}/history/{$promptId}");

                if (! $historyResponse->successful()) {
                    continue;
                }

                $history = $historyResponse->json();

                if (! isset($history[$promptId]['outputs'][$outputNodeId]['images'][0])) {
                    continue;
                }

                $imageInfo = $history[$promptId]['outputs'][$outputNodeId]['images'][0];
                $filename = $imageInfo['filename'];
                $subfolder = $imageInfo['subfolder'] ?? '';
                $type = $imageInfo['type'] ?? 'output';

                $imageResponse = Http::timeout(30)
                    ->connectTimeout(5)
                    ->get("{$baseUrl}/view", [
                        'filename' => $filename,
                        'subfolder' => $subfolder,
                        'type' => $type,
                    ]);

                $imageResponse->throw();

                return base64_encode($imageResponse->body());
            } catch (\Throwable $e) {
                Log::warning('[ImageGenerationService] ComfyUI poll attempt failed', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException("ComfyUI image generation timed out after {$maxAttempts} attempts");
    }

    /**
     * Generate an image via the Replicate API.
     *
     * @param  array<string, mixed>  $options
     * @return array{image_data: string, provider: string, model: string, metadata: array<string, mixed>}
     */
    private function generateViaReplicate(string $prompt, array $options): array
    {
        $apiKey = (string) config('services.replicate.api_key', '');
        $model = $options['model'] ?? (string) config('services.replicate.default_model', 'black-forest-labs/flux-schnell');
        $dimensions = $this->mapAspectRatio($options['aspect_ratio'] ?? '1:1');

        $payload = [
            'input' => [
                'prompt' => $prompt,
                'negative_prompt' => $options['negative_prompt'] ?? '',
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'num_outputs' => 1,
            ],
        ];

        $createResponse = Http::withToken($apiKey)
            ->timeout(30)
            ->connectTimeout(10)
            ->post("https://api.replicate.com/v1/models/{$model}/predictions", $payload);

        $createResponse->throw();

        $predictionId = $createResponse->json('id');

        if (! $predictionId) {
            throw new \RuntimeException('Replicate did not return a prediction ID');
        }

        // Poll for completion.
        $maxAttempts = 60;
        $attempt = 0;
        $outputUrl = null;

        while ($attempt < $maxAttempts) {
            sleep(2);
            $attempt++;

            $statusResponse = Http::withToken($apiKey)
                ->timeout(15)
                ->connectTimeout(5)
                ->get("https://api.replicate.com/v1/predictions/{$predictionId}");

            if (! $statusResponse->successful()) {
                continue;
            }

            $status = $statusResponse->json('status');

            if ($status === 'succeeded') {
                $output = $statusResponse->json('output');
                $outputUrl = is_array($output) ? ($output[0] ?? null) : $output;
                break;
            }

            if ($status === 'failed' || $status === 'canceled') {
                throw new \RuntimeException('Replicate prediction failed: '.$statusResponse->json('error', 'unknown error'));
            }
        }

        if ($outputUrl === null) {
            throw new \RuntimeException('Replicate prediction timed out');
        }

        $imageResponse = Http::timeout(30)->connectTimeout(10)->get((string) $outputUrl);
        $imageResponse->throw();

        return [
            'image_data' => base64_encode($imageResponse->body()),
            'provider' => 'replicate',
            'model' => $model,
            'metadata' => [
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'aspect_ratio' => $options['aspect_ratio'] ?? '1:1',
                'prediction_id' => $predictionId,
            ],
        ];
    }

    /**
     * Generate an image via the Stability AI API.
     *
     * @param  array<string, mixed>  $options
     * @return array{image_data: string, provider: string, model: string, metadata: array<string, mixed>}
     */
    private function generateViaStability(string $prompt, array $options): array
    {
        $apiKey = (string) config('services.stability.api_key', '');
        $model = $options['model'] ?? (string) config('services.stability.default_model', 'stable-diffusion-xl-1024-v1-0');
        $dimensions = $this->mapAspectRatio($options['aspect_ratio'] ?? '1:1');

        $stylePresets = [
            'photorealistic' => 'photographic',
            'artistic' => 'enhance',
            'illustration' => 'digital-art',
            'cinematic' => 'cinematic',
        ];

        $payload = [
            'text_prompts' => [
                ['text' => $prompt, 'weight' => 1.0],
            ],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'samples' => 1,
            'steps' => $options['quality'] === 'ultra' ? 50 : ($options['quality'] === 'high' ? 35 : 20),
            'cfg_scale' => 7.0,
        ];

        if (isset($options['negative_prompt']) && $options['negative_prompt'] !== '') {
            $payload['text_prompts'][] = ['text' => $options['negative_prompt'], 'weight' => -1.0];
        }

        if (isset($options['style']) && isset($stylePresets[$options['style']])) {
            $payload['style_preset'] = $stylePresets[$options['style']];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Accept' => 'application/json',
        ])
            ->timeout(120)
            ->connectTimeout(10)
            ->post("https://api.stability.ai/v1/generation/{$model}/text-to-image", $payload);

        $response->throw();

        $artifacts = $response->json('artifacts');

        if (! is_array($artifacts) || empty($artifacts)) {
            throw new \RuntimeException('Stability AI returned no image artifacts');
        }

        $imageData = $artifacts[0]['base64'] ?? '';

        if ($imageData === '') {
            throw new \RuntimeException('Stability AI returned empty image data');
        }

        return [
            'image_data' => $imageData,
            'provider' => 'stability',
            'model' => $model,
            'metadata' => [
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'aspect_ratio' => $options['aspect_ratio'] ?? '1:1',
                'finish_reason' => $artifacts[0]['finishReason'] ?? null,
                'seed' => $artifacts[0]['seed'] ?? null,
            ],
        ];
    }

    /**
     * Generate an image via OpenAI DALL-E.
     *
     * @param  array<string, mixed>  $options
     * @return array{image_data: string, provider: string, model: string, metadata: array<string, mixed>}
     */
    private function generateViaOpenAi(string $prompt, array $options): array
    {
        $apiKey = (string) config('services.openai.api_key', '');
        $model = $options['model'] ?? 'dall-e-3';
        $aspectRatio = $options['aspect_ratio'] ?? '1:1';

        $sizeMap = [
            '1:1' => '1024x1024',
            '16:9' => '1792x1024',
            '9:16' => '1024x1792',
            '4:3' => '1024x1024',
            '3:2' => '1792x1024',
        ];

        $qualityMap = [
            'standard' => 'standard',
            'high' => 'hd',
            'ultra' => 'hd',
        ];

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $sizeMap[$aspectRatio] ?? '1024x1024',
            'quality' => $qualityMap[$options['quality'] ?? 'standard'] ?? 'standard',
            'response_format' => 'b64_json',
        ];

        if (isset($options['style'])) {
            $payload['style'] = in_array($options['style'], ['vivid', 'natural'], true)
                ? $options['style']
                : 'vivid';
        }

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->connectTimeout(10)
            ->post('https://api.openai.com/v1/images/generations', $payload);

        $response->throw();

        $imageData = $response->json('data.0.b64_json');

        if (! $imageData) {
            throw new \RuntimeException('OpenAI DALL-E returned no image data');
        }

        return [
            'image_data' => $imageData,
            'provider' => 'openai',
            'model' => $model,
            'metadata' => [
                'size' => $sizeMap[$aspectRatio] ?? '1024x1024',
                'aspect_ratio' => $aspectRatio,
                'revised_prompt' => $response->json('data.0.revised_prompt'),
            ],
        ];
    }

    /**
     * Convert an aspect ratio string to pixel dimensions.
     *
     * @return array{width: int, height: int}
     */
    private function mapAspectRatio(string $ratio): array
    {
        return match ($ratio) {
            '16:9' => ['width' => 1344, 'height' => 768],
            '9:16' => ['width' => 768, 'height' => 1344],
            '4:3' => ['width' => 1152, 'height' => 896],
            '3:2' => ['width' => 1216, 'height' => 832],
            default => ['width' => 1024, 'height' => 1024], // 1:1
        };
    }

    private function isComfyUiAvailable(): bool
    {
        try {
            $baseUrl = rtrim((string) config('services.comfyui.base_url', ''), '/');

            if ($baseUrl === '') {
                return false;
            }

            $response = Http::timeout(3)->connectTimeout(2)->get("{$baseUrl}/system_stats");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('[ImageGenerationService] ComfyUI availability check failed: '.$e->getMessage());

            return false;
        }
    }

    private function isReplicateConfigured(): bool
    {
        $key = (string) config('services.replicate.api_key', '');

        return $key !== '';
    }

    private function isStabilityConfigured(): bool
    {
        $key = (string) config('services.stability.api_key', '');

        return $key !== '';
    }

    private function isOpenAiConfigured(): bool
    {
        $key = (string) config('services.openai.api_key', '');

        return $key !== '';
    }
}
