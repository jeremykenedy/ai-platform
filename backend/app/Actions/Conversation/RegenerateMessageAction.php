<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Jobs\StreamInferenceJob;
use App\Models\Message;
use App\Services\AI\ContextWindowService;
use App\Services\AI\EmbeddingService;
use App\Services\Memory\MemoryRetrievalService;
use Illuminate\Validation\ValidationException;

class RegenerateMessageAction
{
    public function __construct(
        private readonly ContextWindowService $contextWindowService,
        private readonly MemoryRetrievalService $memoryRetrievalService,
        private readonly EmbeddingService $embeddingService,
    ) {
    }

    /**
     * Soft-delete the last assistant message and re-dispatch inference.
     *
     * @throws ValidationException
     */
    public function handle(Message $message): void
    {
        if ($message->role !== 'assistant') {
            throw ValidationException::withMessages([
                'message' => ['Only assistant messages can be regenerated.'],
            ]);
        }

        $conversation = $message->conversation;

        $lastAssistant = $conversation->messages()
            ->where('role', 'assistant')
            ->orderByDesc('sequence')
            ->first();

        if ($lastAssistant === null || (string) $lastAssistant->id !== (string) $message->id) {
            throw ValidationException::withMessages([
                'message' => ['Only the last assistant message can be regenerated.'],
            ]);
        }

        $message->delete();

        $userMessage = $conversation->messages()
            ->where('role', 'user')
            ->orderByDesc('sequence')
            ->first();

        if ($userMessage === null) {
            return;
        }

        $userId = (string) $conversation->user_id;
        $memories = $this->memoryRetrievalService->retrieveForMessage($userId, $userMessage->content);
        $systemPrompt = $this->memoryRetrievalService->formatAsSystemPrompt($memories);
        $context = $this->contextWindowService->buildContext($conversation, $userMessage->content, $systemPrompt);

        $temperatureVariation = (random_int(0, 1) === 0 ? 0.1 : -0.1);

        StreamInferenceJob::dispatch(
            $conversation->id,
            $userMessage->id,
            $context,
            $conversation->model_name,
            ['temperature_variation' => $temperatureVariation],
        );
    }
}
