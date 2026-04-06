<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StreamingService
{
    public function __construct(
        private readonly ModelRouterService $modelRouter,
    ) {
    }

    /**
     * Stream a chat completion for the given conversation.
     *
     * Iterates the provider's Generator, accumulates content, and returns
     * a summary array. Callers (jobs) are responsible for broadcasting each
     * token via events.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed>                             $options
     *
     * @return array{content: string, tokens_used: int, finish_reason: string, sequence: int}
     */
    public function streamChat(string $conversationId, array $messages, string $model, array $options = []): array
    {
        $this->resetSequence($conversationId);

        $route = $this->modelRouter->route($model, $options['context'] ?? []);
        $provider = $route['provider'];
        $resolvedModel = $route['model'];

        $fullContent = '';
        $tokensUsed = 0;
        $finishReason = 'stop';
        $sequence = 0;

        try {
            $generator = $provider->stream($messages, $resolvedModel, $options);

            foreach ($generator as $chunk) {
                if ($this->isStreamCancelled($conversationId)) {
                    $finishReason = 'cancelled';
                    break;
                }

                if (is_array($chunk) && isset($chunk['__finish__'])) {
                    $finishReason = (string) ($chunk['finish_reason'] ?? 'stop');

                    if (isset($chunk['tokens_used'])) {
                        $tokensUsed = (int) $chunk['tokens_used'];
                    }

                    break;
                }

                $token = (string) $chunk;
                $fullContent .= $token;
                $sequence = $this->trackSequence($conversationId);
            }
        } catch (\Throwable $e) {
            Log::error('[StreamingService] Stream interrupted', [
                'conversation_id' => $conversationId,
                'model'           => $resolvedModel,
                'error'           => $e->getMessage(),
            ]);

            $finishReason = 'error';
        }

        if ($tokensUsed === 0 && $fullContent !== '') {
            $tokensUsed = $this->estimateTokens($fullContent);
        }

        $this->clearCancellationFlag($conversationId);

        return [
            'content'       => $fullContent,
            'tokens_used'   => $tokensUsed,
            'finish_reason' => $finishReason,
            'sequence'      => $sequence,
        ];
    }

    /**
     * Set a cancellation flag in Redis so the stream loop will stop.
     */
    public function cancelStream(string $conversationId): void
    {
        Cache::put(
            $this->cancellationKey($conversationId),
            true,
            now()->addMinutes(5),
        );
    }

    /**
     * Check whether a cancellation has been requested for this conversation.
     */
    public function isStreamCancelled(string $conversationId): bool
    {
        return (bool) Cache::get($this->cancellationKey($conversationId), false);
    }

    /**
     * Increment the sequence counter and return the new value.
     */
    private function trackSequence(string $conversationId): int
    {
        $key = $this->sequenceKey($conversationId);

        return (int) Cache::increment($key);
    }

    /**
     * Clear the sequence counter for a conversation.
     */
    private function resetSequence(string $conversationId): void
    {
        Cache::forget($this->sequenceKey($conversationId));
    }

    /**
     * Remove the cancellation flag after the stream completes.
     */
    private function clearCancellationFlag(string $conversationId): void
    {
        Cache::forget($this->cancellationKey($conversationId));
    }

    private function cancellationKey(string $conversationId): string
    {
        return "stream:cancel:{$conversationId}";
    }

    private function sequenceKey(string $conversationId): string
    {
        return "stream:seq:{$conversationId}";
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(str_word_count($text) * 1.3);
    }
}
