<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\ConversationSummary;
use App\Models\Message;
use App\Services\AI\ModelRouterService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ConversationSummaryService
{
    private const MIN_MESSAGES_TO_SUMMARIZE = 5;

    private const MESSAGES_SINCE_LAST_SUMMARY_THRESHOLD = 20;

    private const CONTEXT_WINDOW_USAGE_THRESHOLD = 0.80;

    public function __construct(
        private readonly ModelRouterService $modelRouter,
    ) {
    }

    /**
     * Summarize messages in the conversation since the last summary.
     *
     * Returns null when there are too few messages to warrant a summary.
     */
    public function summarize(Conversation $conversation): ?ConversationSummary
    {
        $messages = $this->getMessagesSinceLastSummary($conversation);

        if ($messages->count() < self::MIN_MESSAGES_TO_SUMMARIZE) {
            return null;
        }

        $llmMessages = $this->buildSummarizationPrompt($messages);
        $route = $this->modelRouter->route('auto');

        try {
            $response = $route['provider']->chat($llmMessages, $route['model'], [
                'format'     => 'json',
                'max_tokens' => 1024,
            ]);

            $decoded = json_decode(trim($response['content']), true);

            if (!is_array($decoded) || !isset($decoded['summary']) || !is_string($decoded['summary'])) {
                Log::warning('[ConversationSummaryService] Invalid summary response for conversation '.$conversation->id);

                return null;
            }

            $summaryText = trim($decoded['summary']);

            if ($summaryText === '') {
                return null;
            }

            $messageIds = $messages->pluck('id')->map(fn ($id): string => (string) $id)->all();

            /** @var ConversationSummary $summary */
            $summary = ConversationSummary::create([
                'conversation_id'    => (string) $conversation->id,
                'content'            => $summaryText,
                'covers_message_ids' => $messageIds,
                'message_count'      => $messages->count(),
            ]);

            return $summary;
        } catch (\Throwable $e) {
            Log::error('[ConversationSummaryService] Summarization failed: '.$e->getMessage(), [
                'conversation_id' => (string) $conversation->id,
            ]);

            return null;
        }
    }

    /**
     * Determine whether the conversation warrants a new summary.
     */
    public function shouldSummarize(Conversation $conversation): bool
    {
        $messagesSinceLastSummary = $this->getMessagesSinceLastSummary($conversation)->count();

        if ($messagesSinceLastSummary >= self::MESSAGES_SINCE_LAST_SUMMARY_THRESHOLD) {
            return true;
        }

        $contextWindowUsed = (int) $conversation->context_window_used;

        if ($contextWindowUsed > 0) {
            $model = AiModel::where('model_id', $conversation->model_name)->first();
            $contextLimit = $model?->context_window ?? 0;

            if ($contextLimit > 0 && ($contextWindowUsed / $contextLimit) > self::CONTEXT_WINDOW_USAGE_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the most recent summary for the conversation.
     */
    public function getLatestSummary(Conversation $conversation): ?ConversationSummary
    {
        return ConversationSummary::where('conversation_id', (string) $conversation->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Return the total number of summaries for the conversation.
     */
    public function getSummaryCount(Conversation $conversation): int
    {
        return ConversationSummary::where('conversation_id', (string) $conversation->id)->count();
    }

    /**
     * Get all messages created since the last summary (or all messages if none).
     *
     * @return Collection<int, Message>
     */
    private function getMessagesSinceLastSummary(Conversation $conversation): Collection
    {
        $latest = $this->getLatestSummary($conversation);

        $query = Message::where('conversation_id', (string) $conversation->id)
            ->orderBy('sequence');

        if ($latest !== null) {
            $coveredIds = $latest->covers_message_ids ?? [];

            if (!empty($coveredIds)) {
                $query->whereNotIn('id', $coveredIds);
            }
        }

        return $query->get();
    }

    /**
     * Build the messages array to send to the LLM for summarization.
     *
     * @param Collection<int, Message> $messages
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function buildSummarizationPrompt(Collection $messages): array
    {
        $conversationText = $messages->map(function (Message $message): string {
            $role = strtoupper($message->role);
            $content = $message->content ?? '';

            return "{$role}: {$content}";
        })->implode("\n");

        return [
            [
                'role'    => 'user',
                'content' => "Summarize the key points of this conversation concisely. Include decisions made, questions asked, topics covered, and any action items. Respond with only JSON: {\"summary\": \"...\"}\n\n{$conversationText}",
            ],
        ];
    }
}
