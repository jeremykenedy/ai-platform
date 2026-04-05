<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Memory;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public function __construct(
        private readonly ModelRouterService $modelRouter,
    ) {}

    /**
     * Generate an embedding vector for the given text.
     *
     * Defaults to the configured local embedding model via Ollama.
     *
     * @return float[]
     */
    public function generateEmbedding(string $text, ?string $model = null): array
    {
        $embeddingModel = $model ?? (string) config('ai.default_embedding_model', 'nomic-embed-text:latest');

        try {
            $provider = $this->modelRouter->resolveProvider('ollama');

            return $provider->embed($text, $embeddingModel);
        } catch (\Throwable $e) {
            Log::error('[EmbeddingService] Failed to generate embedding', [
                'model' => $embeddingModel,
                'text_length' => strlen($text),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate an embedding for the message's content and persist it via pgvector.
     */
    public function storeMessageEmbedding(Message $message): void
    {
        if ($message->content === '' || $message->content === null) {
            return;
        }

        try {
            $embedding = $this->generateEmbedding($message->content);

            if (empty($embedding)) {
                return;
            }

            $vector = $this->formatVector($embedding);

            DB::statement(
                'UPDATE messages SET embedding = ? WHERE id = ?',
                [$vector, $message->id],
            );
        } catch (\Throwable $e) {
            Log::error('[EmbeddingService] Failed to store message embedding', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate an embedding for the memory's content and persist it via pgvector.
     */
    public function storeMemoryEmbedding(Memory $memory): void
    {
        if ($memory->content === '' || $memory->content === null) {
            return;
        }

        try {
            $embedding = $this->generateEmbedding($memory->content);

            if (empty($embedding)) {
                return;
            }

            $vector = $this->formatVector($embedding);

            DB::statement(
                'UPDATE memories SET embedding = ? WHERE id = ?',
                [$vector, $memory->id],
            );
        } catch (\Throwable $e) {
            Log::error('[EmbeddingService] Failed to store memory embedding', [
                'memory_id' => $memory->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find similar records in the given table using pgvector cosine distance.
     *
     * @param  float[]  $queryEmbedding
     * @return Collection<int, object>
     */
    public function findSimilar(array $queryEmbedding, string $table, string $userId, int $limit = 15): Collection
    {
        $vector = $this->formatVector($queryEmbedding);

        try {
            $results = DB::select(
                "SELECT *, embedding <=> ? AS distance
                 FROM {$table}
                 WHERE user_id = ?
                   AND is_active = true
                   AND embedding IS NOT NULL
                 ORDER BY embedding <=> ?
                 LIMIT ?",
                [$vector, $userId, $vector, $limit],
            );

            return collect($results);
        } catch (\Throwable $e) {
            Log::error('[EmbeddingService] Similarity search failed', [
                'table' => $table,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * Returns a value between -1.0 and 1.0 (1.0 = identical direction).
     *
     * @param  float[]  $a
     * @param  float[]  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $count = min(count($a), count($b));

        if ($count === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator === 0.0) {
            return 0.0;
        }

        return $dot / $denominator;
    }

    /**
     * Format a float array as a pgvector-compatible string literal.
     *
     * @param  float[]  $embedding
     */
    private function formatVector(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }
}
