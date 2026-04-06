<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Models\Conversation;
use App\Models\Memory;
use App\Models\MemoryConflict;
use App\Services\AI\EmbeddingService;
use App\Services\AI\ModelRouterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemoryExtractionService
{
    public function __construct(
        private readonly ModelRouterService $modelRouter,
        private readonly EmbeddingService $embeddingService,
    ) {}

    /**
     * Extract memories from a conversation and persist them.
     *
     * @return string[] IDs of created or updated memories
     */
    public function extractFromConversation(Conversation $conversation): array
    {
        $conversation->loadMissing(['messages', 'user']);

        $messages = $conversation->messages->sortBy('sequence')->values();

        if ($messages->isEmpty()) {
            return [];
        }

        $userId = (string) $conversation->user_id;
        $route = $this->modelRouter->route('auto');
        $prompt = $this->buildExtractionPrompt($messages->toArray());

        $systemPrompt = 'Extract key facts and preferences about the user from this conversation. Return JSON array: [{"content": "...", "category": "preference|fact|instruction|context|personality", "importance": 1-10}]. Only include information worth remembering long-term.';

        $candidates = null;
        $lastError = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $llmMessages = [
                    ['role' => 'user', 'content' => $prompt],
                ];

                if ($attempt > 1 && $lastError !== null) {
                    $llmMessages[] = [
                        'role' => 'user',
                        'content' => 'Your previous response could not be parsed as valid JSON. Please respond with ONLY a valid JSON array matching the schema: [{"content": "string", "category": "preference|fact|instruction|context|personality", "importance": 1-10}].',
                    ];
                }

                $response = $route['provider']->chat($llmMessages, $route['model'], [
                    'system' => $systemPrompt,
                    'format' => 'json',
                    'max_tokens' => 2048,
                ]);

                $candidates = $this->validateExtractionResponse($response['content']);
                $lastError = null;
                break;
            } catch (\InvalidArgumentException $e) {
                $lastError = $e;
                Log::warning('[MemoryExtractionService] JSON parse failed on attempt '.$attempt.': '.$e->getMessage());
            } catch (\Throwable $e) {
                Log::error('[MemoryExtractionService] LLM call failed: '.$e->getMessage());

                return [];
            }
        }

        if ($candidates === null) {
            Log::error('[MemoryExtractionService] Failed to parse extraction response after 3 attempts.');

            return [];
        }

        $createdOrUpdatedIds = [];

        foreach ($candidates as $memoryData) {
            try {
                $embedding = $this->embeddingService->generateEmbedding($memoryData['content']);
                $memory = $this->createOrUpdateMemory(
                    $userId,
                    $memoryData,
                    $embedding,
                    (string) $conversation->id,
                    null,
                );
                $createdOrUpdatedIds[] = (string) $memory->id;
            } catch (\Throwable $e) {
                Log::error('[MemoryExtractionService] Failed to persist memory candidate: '.$e->getMessage(), [
                    'content' => $memoryData['content'] ?? '',
                ]);
            }
        }

        return $createdOrUpdatedIds;
    }

    /**
     * Parse and validate that the JSON response matches the expected schema.
     *
     * @return array<int, array{content: string, category: string, importance: int}>
     *
     * @throws \InvalidArgumentException
     */
    public function validateExtractionResponse(string $json): array
    {
        $decoded = json_decode(trim($json), true);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('Response is not a JSON array.');
        }

        $validCategories = ['preference', 'fact', 'instruction', 'context', 'personality'];
        $validated = [];

        foreach ($decoded as $index => $item) {
            if (! is_array($item)) {
                throw new \InvalidArgumentException("Item at index {$index} is not an object.");
            }

            if (! isset($item['content']) || ! is_string($item['content']) || trim($item['content']) === '') {
                throw new \InvalidArgumentException("Item at index {$index} missing or empty 'content'.");
            }

            if (! isset($item['category']) || ! in_array($item['category'], $validCategories, true)) {
                throw new \InvalidArgumentException("Item at index {$index} has invalid 'category': ".($item['category'] ?? 'missing'));
            }

            $importance = isset($item['importance']) ? (int) $item['importance'] : 5;
            $importance = max(1, min(10, $importance));

            $validated[] = [
                'content' => trim($item['content']),
                'category' => $item['category'],
                'importance' => $importance,
            ];
        }

        return $validated;
    }

    /**
     * Build the extraction prompt from conversation messages.
     *
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function buildExtractionPrompt(array $messages): string
    {
        $lines = ['Here is the conversation to analyze:'];
        $lines[] = '';

        foreach ($messages as $message) {
            $role = strtoupper((string) ($message['role'] ?? 'unknown'));
            $content = (string) ($message['content'] ?? '');

            if ($content === '') {
                continue;
            }

            $lines[] = "{$role}: {$content}";
        }

        $lines[] = '';
        $lines[] = 'Extract key facts, preferences, instructions, and context about the USER (not the assistant). Return only a JSON array.';

        return implode("\n", $lines);
    }

    /**
     * Find the most similar existing memory above the given threshold.
     */
    private function findDuplicates(string $userId, float $threshold = 0.92): ?Memory
    {
        return null;
    }

    /**
     * Check for a near-duplicate memory given an embedding vector.
     *
     * Uses raw pgvector query for performance.
     *
     * @param  float[]  $embedding
     */
    private function findDuplicateByEmbedding(string $userId, array $embedding, float $threshold = 0.92): ?Memory
    {
        $vectorLiteral = '['.implode(',', $embedding).']';

        $row = DB::selectOne(
            'SELECT id, (1 - (embedding <=> ?::vector)) AS similarity
             FROM memories
             WHERE user_id = ?
               AND is_active = true
               AND deleted_at IS NULL
             ORDER BY embedding <=> ?::vector
             LIMIT 1',
            [$vectorLiteral, $userId, $vectorLiteral],
        );

        if ($row === null) {
            return null;
        }

        $similarity = (float) $row->similarity;

        if ($similarity >= $threshold) {
            return Memory::where('id', $row->id)->first();
        }

        return null;
    }

    /**
     * Detect a conflicting memory (similarity in the 0.7-0.92 range).
     *
     * @param  float[]  $embedding
     */
    private function detectConflicts(string $userId, array $embedding): ?Memory
    {
        $vectorLiteral = '['.implode(',', $embedding).']';

        $row = DB::selectOne(
            'SELECT id, (1 - (embedding <=> ?::vector)) AS similarity
             FROM memories
             WHERE user_id = ?
               AND is_active = true
               AND deleted_at IS NULL
             ORDER BY embedding <=> ?::vector
             LIMIT 1',
            [$vectorLiteral, $userId, $vectorLiteral],
        );

        if ($row === null) {
            return null;
        }

        $similarity = (float) $row->similarity;

        if ($similarity >= 0.7 && $similarity < 0.92) {
            return Memory::where('id', $row->id)->first();
        }

        return null;
    }

    /**
     * Create a new memory or update an existing one, handling duplicate/conflict detection.
     *
     * @param  array{content: string, category: string, importance: int}  $memoryData
     * @param  float[]  $embedding
     */
    private function createOrUpdateMemory(
        string $userId,
        array $memoryData,
        array $embedding,
        ?string $conversationId,
        ?string $messageId,
    ): Memory {
        $duplicate = $this->findDuplicateByEmbedding($userId, $embedding, 0.92);

        if ($duplicate !== null) {
            $duplicate->update([
                'content' => $memoryData['content'],
                'importance' => max($duplicate->importance, $memoryData['importance']),
                'last_accessed_at' => now(),
            ]);

            return $duplicate;
        }

        $conflict = $this->detectConflicts($userId, $embedding);

        $vectorLiteral = '['.implode(',', $embedding).']';

        /** @var Memory $memory */
        $memory = Memory::create([
            'user_id' => $userId,
            'content' => $memoryData['content'],
            'category' => $memoryData['category'],
            'importance' => $memoryData['importance'],
            'source_conversation_id' => $conversationId,
            'source_message_id' => $messageId,
            'embedding' => $vectorLiteral,
            'is_active' => true,
            'last_accessed_at' => now(),
            'access_count' => 0,
        ]);

        if ($conflict !== null) {
            MemoryConflict::create([
                'user_id' => $userId,
                'memory_id' => (string) $memory->id,
                'conflicts_with' => (string) $conflict->id,
                'resolved' => false,
            ]);
        }

        return $memory;
    }
}
