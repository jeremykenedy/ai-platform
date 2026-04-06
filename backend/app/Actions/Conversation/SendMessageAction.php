<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Jobs\StreamInferenceJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Services\AI\ContextWindowService;
use App\Services\AI\EmbeddingService;
use App\Services\Memory\MemoryRetrievalService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SendMessageAction
{
    public function __construct(
        private readonly ContextWindowService $contextWindowService,
        private readonly MemoryRetrievalService $memoryRetrievalService,
        private readonly EmbeddingService $embeddingService,
    ) {
    }

    /**
     * Persist the user message, handle attachments, and dispatch streaming inference.
     *
     * @param array<int, array{file: UploadedFile, mime_type?: string}> $attachments
     */
    public function handle(
        Conversation $conversation,
        string $content,
        ?string $model = null,
        array $attachments = [],
    ): Message {
        $sequence = $conversation->messages()->max('sequence') + 1;

        /** @var Message $message */
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role'            => 'user',
            'content'         => $content,
            'sequence'        => $sequence,
        ]);

        foreach ($attachments as $attachment) {
            $file = $attachment['file'];
            $path = Storage::disk('local')->putFile('attachments/'.$conversation->id, $file);

            MessageAttachment::create([
                'message_id'        => $message->id,
                'disk'              => 'local',
                'path'              => $path,
                'filename'          => $file->getClientOriginalName(),
                'mime_type'         => $file->getMimeType() ?? ($attachment['mime_type'] ?? 'application/octet-stream'),
                'size'              => $file->getSize(),
                'extraction_status' => 'pending',
            ]);
        }

        $userId = (string) $conversation->user_id;
        $memories = $this->memoryRetrievalService->retrieveForMessage($userId, $content);
        $systemPrompt = $this->memoryRetrievalService->formatAsSystemPrompt($memories);
        $context = $this->contextWindowService->buildContext($conversation, $content, $systemPrompt);

        StreamInferenceJob::dispatch(
            $conversation->id,
            $message->id,
            $context,
            $model ?? $conversation->model_name,
        );

        return $message;
    }
}
