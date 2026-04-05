<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\Memory\MemoryExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExtractMemoriesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly string $conversationId,
    ) {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return $this->conversationId;
    }

    public function handle(MemoryExtractionService $memoryExtractionService): void
    {
        /** @var Conversation $conversation */
        $conversation = Conversation::with('user')->findOrFail($this->conversationId);

        $extractedIds = $memoryExtractionService->extractFromConversation($conversation);

        Log::info('[ExtractMemoriesJob] Memories extracted', [
            'conversation_id' => $this->conversationId,
            'memory_count' => count($extractedIds),
            'memory_ids' => $extractedIds,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[ExtractMemoriesJob] Job failed', [
            'conversation_id' => $this->conversationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
