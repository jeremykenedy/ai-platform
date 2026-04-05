<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Models\Memory;
use App\Models\MemoryConflict;
use App\Services\AI\EmbeddingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemoryRetrievalService
{
    public function __construct(
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * Retrieve the most relevant memories for a given user message.
     *
     * Scoring combines cosine similarity, importance, and recency.
     *
     * @return Collection<int, Memory>
     */
    public function retrieveForMessage(string $userId, string $messageContent, int $limit = 15): Collection
    {
        try {
            $embedding = $this->embeddingService->embed($messageContent);
        } catch (\Throwable $e) {
            Log::warning('[MemoryRetrievalService] Embedding generation failed: '.$e->getMessage());

            return collect();
        }

        $vectorLiteral = '['.implode(',', $embedding).']';

        $rows = DB::select(
            "SELECT id,
                    (1 - (embedding <=> ?::vector)) AS similarity
             FROM memories
             WHERE user_id = ?
               AND is_active = true
               AND deleted_at IS NULL
             ORDER BY (1 - (embedding <=> ?::vector))
                      * (importance / 10.0)
                      * (CASE
                             WHEN last_accessed_at > NOW() - INTERVAL '7 days'  THEN 1.2
                             WHEN last_accessed_at > NOW() - INTERVAL '30 days' THEN 1.0
                             ELSE 0.8
                         END)
             DESC
             LIMIT ?",
            [$vectorLiteral, $userId, $vectorLiteral, $limit],
        );

        if (empty($rows)) {
            return collect();
        }

        $ids = array_column($rows, 'id');

        Memory::whereIn('id', $ids)->update([
            'last_accessed_at' => now(),
            'access_count' => DB::raw('access_count + 1'),
        ]);

        $orderedIds = $ids;

        return Memory::whereIn('id', $orderedIds)
            ->get()
            ->sortBy(fn (Memory $m): int => (int) array_search((string) $m->id, array_map('strval', $orderedIds), true))
            ->values();
    }

    /**
     * Format a collection of memories as a system prompt prefix.
     *
     * @param  Collection<int, Memory>  $memories
     */
    public function formatAsSystemPrompt(Collection $memories, string $timezone = 'America/Los_Angeles'): string
    {
        $now = Carbon::now($timezone);
        $datetime = $now->format('l, F j, Y \a\t g:i A T');

        if ($memories->isEmpty()) {
            return "Current date and time: {$datetime}. Timezone: {$timezone}.";
        }

        $lines = [];
        $lines[] = "Current date and time: {$datetime}. Timezone: {$timezone}.";
        $lines[] = '';
        $lines[] = 'Things I remember about you:';

        foreach ($memories as $memory) {
            $lines[] = '- '.$memory->content;
        }

        $lines[] = '';
        $lines[] = 'Use this context naturally. Do not explicitly reference that you remember things unless asked.';

        return implode("\n", $lines);
    }

    /**
     * Count active memories for the given user.
     */
    public function getMemoryCount(string $userId): int
    {
        return Memory::where('user_id', $userId)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Get all unresolved memory conflicts for the given user.
     *
     * @return Collection<int, MemoryConflict>
     */
    public function getConflicts(string $userId): Collection
    {
        return MemoryConflict::where('user_id', $userId)
            ->unresolved()
            ->with(['memory', 'conflictingMemory'])
            ->get();
    }
}
