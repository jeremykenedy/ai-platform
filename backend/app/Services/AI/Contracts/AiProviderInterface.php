<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

interface AiProviderInterface
{
    /**
     * Send a chat completion request.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     *
     * @return array{content: string, tokens_used: int, finish_reason: string}
     */
    public function chat(array $messages, string $model, array $options = []): array;

    /**
     * Stream a chat completion, yielding token strings.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     */
    public function stream(array $messages, string $model, array $options = []): \Generator;

    /**
     * Generate an embedding vector for the given text.
     *
     * @return float[]
     */
    public function embed(string $text, ?string $model = null): array;

    /**
     * List available models from the provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array;

    /**
     * Check whether the provider supports a given capability.
     *
     * Capabilities: chat, streaming, vision, code, reasoning, function_calling,
     * file_analysis, long_context, structured_output, embeddings,
     * image_generation, audio_generation, audio_transcription.
     */
    public function supportsCapability(string $capability): bool;

    /**
     * Return true if the provider is configured and reachable.
     */
    public function isAvailable(): bool;

    /**
     * Run a connectivity test and measure round-trip latency.
     *
     * @return array{success: bool, latency_ms: int, error: string|null}
     */
    public function testConnection(): array;
}
