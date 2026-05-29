<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\MessageCompleted;
use App\Events\MessageCreated;
use App\Events\MessageStreamChunk;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\ContextWindowService;
use App\Services\AI\StreamingService;
use App\Services\Memory\MemoryRetrievalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StreamInferenceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var array<int, array{role: string, content: string}> */
    public array $context;

    /** @var array<string, mixed> */
    public array $options;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var int[] */
    public array $backoff = [1, 5, 10];

    /**
     * @param array<int, array{role: string, content: string}> $context
     * @param array<string, mixed>                             $options
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly string $messageId,
        array $context,
        public readonly ?string $modelName = null,
        array $options = [],
    ) {
        $this->onQueue('inference');
        $this->context = $context;
        $this->options = $options;
    }

    public function handle(
        StreamingService $streamingService,
        ContextWindowService $contextWindowService,
        MemoryRetrievalService $memoryRetrievalService,
    ): void {
        $conversation = Conversation::findOrFail($this->conversationId);
        $userMessage = Message::findOrFail($this->messageId);

        $model = $this->modelName ?? (string) $conversation->model_name;
        $sequence = (int) $conversation->messages()->max('sequence') + 1;

        /** @var Message $assistantMessage */
        $assistantMessage = Message::create([
            'conversation_id' => $this->conversationId,
            'role'            => 'assistant',
            'content'         => '',
            'model_version'   => $model,
            'sequence'        => $sequence,
        ]);

        broadcast(new MessageCreated(
            conversationId: $this->conversationId,
            messageId: (string) $assistantMessage->id,
            role: 'assistant',
            content: '',
        ));

        $result = $streamingService->streamChat(
            $this->conversationId,
            $this->context,
            $model,
            array_merge($this->options, ['assistant_message_id' => $assistantMessage->id]),
        );

        $tokensUsed = $contextWindowService->estimateTokens($result['content']) + ($result['tokens_used'] ?? 0);

        $assistantMessage->update([
            'content'       => $result['content'],
            'tokens_used'   => $result['tokens_used'],
            'finish_reason' => $result['finish_reason'],
        ]);

        $conversation->increment('context_window_used', $tokensUsed);

        $assistantMessage->refresh();
        $messagePayload = (new MessageResource($assistantMessage))->resolve();

        broadcast(new MessageCompleted(
            conversationId: $this->conversationId,
            messageId: (string) $assistantMessage->id,
            tokensUsed: (int) $assistantMessage->tokens_used,
            finishReason: (string) $assistantMessage->finish_reason,
            message: $messagePayload,
        ));

        GenerateEmbeddingJob::dispatch(Message::class, (string) $assistantMessage->id);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[StreamInferenceJob] Job failed', [
            'conversation_id' => $this->conversationId,
            'message_id'      => $this->messageId,
            'error'           => $exception->getMessage(),
        ]);

        $userMessage = Message::find($this->messageId);

        if ($userMessage !== null) {
            $userMessage->update(['finish_reason' => 'error']);
        }

        broadcast(new MessageStreamChunk(
            conversationId: $this->conversationId,
            token: '',
            sequence: 0,
        ));
    }
}
