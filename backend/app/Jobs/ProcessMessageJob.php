<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\MessageCompleted;
use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\ModelRouterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMessageJob implements ShouldQueue
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

    /**
     * @param array<int, array{role: string, content: string}> $context
     * @param array<string, mixed>                             $options
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly string $userMessageId,
        array $context,
        public readonly ?string $modelName = null,
        array $options = [],
    ) {
        $this->onQueue('inference');
        $this->context = $context;
        $this->options = $options;
    }

    public function handle(ModelRouterService $modelRouter): void
    {
        $conversation = Conversation::findOrFail($this->conversationId);

        $model = $this->modelName ?? (string) $conversation->model_name;

        $route = $modelRouter->route($model, $this->options['context'] ?? []);
        $provider = $route['provider'];
        $resolvedModel = $route['model'];

        $result = $provider->chat($this->context, $resolvedModel, $this->options);

        /** @var Message $assistantMessage */
        $assistantMessage = Message::create([
            'conversation_id' => $this->conversationId,
            'role'            => 'assistant',
            'content'         => $result['content'],
            'tokens_used'     => $result['tokens_used'],
            'finish_reason'   => $result['finish_reason'],
            'model_version'   => $resolvedModel,
        ]);

        broadcast(new MessageCreated(
            conversationId: $this->conversationId,
            messageId: (string) $assistantMessage->id,
            role: 'assistant',
            content: (string) $result['content'],
        ));

        broadcast(new MessageCompleted(
            conversationId: $this->conversationId,
            messageId: (string) $assistantMessage->id,
            tokensUsed: (int) $result['tokens_used'],
            finishReason: (string) $result['finish_reason'],
        ));

        GenerateEmbeddingJob::dispatch(Message::class, (string) $assistantMessage->id);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[ProcessMessageJob] Job failed', [
            'conversation_id' => $this->conversationId,
            'user_message_id' => $this->userMessageId,
            'error'           => $exception->getMessage(),
        ]);

        $conversation = Conversation::find($this->conversationId);

        if ($conversation !== null) {
            $conversation->update(['context_window_used' => $conversation->context_window_used]);
        }

        $userMessage = Message::find($this->userMessageId);

        if ($userMessage !== null) {
            $userMessage->update(['finish_reason' => 'error']);
        }
    }
}
