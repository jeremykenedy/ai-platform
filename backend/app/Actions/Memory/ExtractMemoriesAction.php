<?php

declare(strict_types=1);

namespace App\Actions\Memory;

use App\Models\Conversation;
use App\Services\Memory\MemoryExtractionService;

class ExtractMemoriesAction
{
    public function __construct(
        private readonly MemoryExtractionService $memoryExtractionService,
    ) {
    }

    /**
     * Extract and persist memories from the given conversation.
     *
     * @return string[] IDs of created or updated memory records
     */
    public function handle(Conversation $conversation): array
    {
        return $this->memoryExtractionService->extractFromConversation($conversation);
    }
}
