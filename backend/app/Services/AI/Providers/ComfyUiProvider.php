<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\AI\AbstractAiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ComfyUiProvider extends AbstractAiProvider
{
    public function __construct()
    {
        parent::__construct(
            (string) config('services.comfyui.base_url', 'http://comfyui:8188'),
        );

        $this->capabilities = ['image_generation'];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{content: string, tokens_used: int, finish_reason: string}
     */
    public function chat(array $messages, string $model, array $options = []): array
    {
        throw new \RuntimeException('ComfyUiProvider does not support chat completions.');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        throw new \RuntimeException('ComfyUiProvider does not support chat streaming.');
    }

    /**
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array
    {
        throw new \RuntimeException('ComfyUiProvider does not support embeddings.');
    }

    /**
     * List available checkpoints and models via the ComfyUI object_info endpoint.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        try {
            $response = Http::withHeaders($this->buildHeaders())
                ->timeout(15)
                ->connectTimeout(5)
                ->get("{$this->baseUrl}/object_info");

            $response->throw();

            $objectInfo = $response->json();

            $checkpoints = $objectInfo['CheckpointLoaderSimple']['input']['required']['ckpt_name'][0] ?? [];
            $loraModels = $objectInfo['LoraLoader']['input']['required']['lora_name'][0] ?? [];

            $models = [];

            foreach ($checkpoints as $checkpoint) {
                $models[] = ['id' => $checkpoint, 'type' => 'checkpoint'];
            }

            foreach ($loraModels as $lora) {
                $models[] = ['id' => $lora, 'type' => 'lora'];
            }

            return $models;
        } catch (\Throwable $e) {
            Log::warning('[ComfyUiProvider] listModels failed: '.$e->getMessage());

            return [];
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->get("{$this->baseUrl}/system_stats");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('[ComfyUiProvider] isAvailable check failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Queue a ComfyUI workflow and poll until images are ready.
     *
     * @param  array<string, mixed>  $workflow  Full ComfyUI workflow JSON (prompt graph)
     * @return array<string, mixed> Output image filenames and metadata
     */
    public function generateImage(array $workflow, string $prompt): array
    {
        $this->injectPromptIntoWorkflow($workflow, $prompt);

        $queueResponse = Http::withHeaders($this->buildHeaders())
            ->timeout(30)
            ->connectTimeout(10)
            ->post("{$this->baseUrl}/prompt", ['prompt' => $workflow]);

        $queueResponse->throw();

        $promptId = $queueResponse->json()['prompt_id'] ?? '';

        if ($promptId === '') {
            throw new \RuntimeException('ComfyUI did not return a prompt_id.');
        }

        return $this->pollHistory($promptId);
    }

    /**
     * Retrieve ComfyUI system statistics.
     *
     * @return array<string, mixed>
     */
    public function getSystemStats(): array
    {
        $response = Http::withHeaders($this->buildHeaders())
            ->timeout(15)
            ->connectTimeout(5)
            ->get("{$this->baseUrl}/system_stats");

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        return ['Content-Type' => 'application/json'];
    }

    /**
     * Poll /history/{promptId} until the generation completes.
     *
     * @return array<string, mixed>
     */
    private function pollHistory(string $promptId): array
    {
        $maxAttempts = 120;
        $sleepSeconds = 2;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders($this->buildHeaders())
                    ->timeout(15)
                    ->connectTimeout(5)
                    ->get("{$this->baseUrl}/history/{$promptId}");

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data[$promptId])) {
                        $result = $data[$promptId];
                        $outputs = $result['outputs'] ?? [];

                        return [
                            'prompt_id' => $promptId,
                            'outputs' => $outputs,
                            'status' => $result['status'] ?? [],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('[ComfyUiProvider] pollHistory attempt '.$attempt.' error: '.$e->getMessage());
            }

            sleep($sleepSeconds);
        }

        throw new \RuntimeException("ComfyUI generation timed out for prompt_id: {$promptId}");
    }

    /**
     * Walk the workflow graph and inject the user prompt into text nodes.
     *
     * @param  array<string, mixed>  $workflow
     */
    private function injectPromptIntoWorkflow(array &$workflow, string $prompt): void
    {
        foreach ($workflow as &$node) {
            if (! is_array($node)) {
                continue;
            }

            $classType = $node['class_type'] ?? '';

            if (in_array($classType, ['CLIPTextEncode', 'CLIPTextEncodeSDXL'], true)) {
                if (isset($node['inputs']['text']) && str_contains((string) $node['inputs']['text'], '{{prompt}}')) {
                    $node['inputs']['text'] = str_replace('{{prompt}}', $prompt, (string) $node['inputs']['text']);
                }
            }
        }
    }
}
