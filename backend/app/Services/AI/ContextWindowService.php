<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\ConversationSummary;
use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

class ContextWindowService
{
    private const DEFAULT_CONTEXT_WINDOW = 8192;

    private const SUMMARIZE_THRESHOLD_PERCENT = 0.80;

    private const SUMMARIZE_MESSAGE_COUNT = 20;

    private const SYSTEM_PROMPT_RESERVE = 512;

    /**
     * Build a context array for a chat completion request.
     *
     * Always includes: system prompt, time awareness, latest summary (if any).
     * Fills remaining window with most recent messages.
     *
     * @param  array<int, array{role: string, content: string}>  $return
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContext(Conversation $conversation, string $userMessage, ?string $systemPrompt = null): array
    {
        $contextWindow = $this->resolveContextWindow($conversation);
        $messages = [];

        $system = $this->buildSystemMessage($conversation, $systemPrompt);
        $messages[] = ['role' => 'system', 'content' => $system];

        $usedTokens = $this->estimateTokens($system) + self::SYSTEM_PROMPT_RESERVE;

        $summary = $this->getLatestSummary($conversation);

        if ($summary !== null) {
            $summaryMessage = "Previous conversation summary:\n{$summary->content}";
            $summaryTokens = $this->estimateTokens($summaryMessage);

            if ($usedTokens + $summaryTokens < $contextWindow) {
                $messages[] = ['role' => 'system', 'content' => $summaryMessage];
                $usedTokens += $summaryTokens;
            }
        }

        $historyMessages = $this->loadRecentMessages($conversation, $summary);
        $userMessageTokens = $this->estimateTokens($userMessage);
        $availableForHistory = $contextWindow - $usedTokens - $userMessageTokens;

        $selectedHistory = $this->selectMessagesWithinBudget($historyMessages, $availableForHistory);

        foreach ($selectedHistory as $message) {
            $messages[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }

    /**
     * Estimate token count for the given text.
     * Uses word count * 1.3 as a reasonable English-language approximation.
     */
    public function estimateTokens(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return (int) ceil(str_word_count($text) * 1.3);
    }

    /**
     * Calculate the percentage of the context window currently used.
     */
    public function getUsagePercentage(Conversation $conversation): float
    {
        $contextWindow = $this->resolveContextWindow($conversation);

        if ($contextWindow === 0) {
            return 0.0;
        }

        $totalTokens = Message::where('conversation_id', $conversation->id)
            ->sum('tokens_used');

        return min(1.0, (float) $totalTokens / $contextWindow);
    }

    /**
     * Returns true when the conversation should be summarized.
     */
    public function shouldSummarize(Conversation $conversation): bool
    {
        if ($this->getUsagePercentage($conversation) >= self::SUMMARIZE_THRESHOLD_PERCENT) {
            return true;
        }

        $lastSummary = $this->getLatestSummary($conversation);
        $afterId = $lastSummary?->id;

        $query = Message::where('conversation_id', $conversation->id);

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        return $query->count() >= self::SUMMARIZE_MESSAGE_COUNT;
    }

    /**
     * Build a time-awareness system prompt fragment.
     */
    public function getTimeAwarenessPrompt(string $timezone = 'America/Los_Angeles'): string
    {
        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        } catch (\Exception $e) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('America/Los_Angeles'));
        }

        $date = $now->format('l, F j, Y');
        $time = $now->format('g:i A');

        return "Current date: {$date}. Current time: {$time}. User timezone: {$timezone}.";
    }

    /**
     * Resolve the context window size for the conversation's model.
     */
    private function resolveContextWindow(Conversation $conversation): int
    {
        if ($conversation->model_name === null) {
            return self::DEFAULT_CONTEXT_WINDOW;
        }

        $aiModel = AiModel::where('name', $conversation->model_name)->first();

        return $aiModel?->context_window ?? self::DEFAULT_CONTEXT_WINDOW;
    }

    /**
     * Build the system message, injecting persona, time awareness, and memory placeholder.
     */
    private function buildSystemMessage(Conversation $conversation, ?string $systemPrompt): string
    {
        $parts = [];

        if ($systemPrompt !== null && $systemPrompt !== '') {
            $parts[] = $systemPrompt;
        } elseif ($conversation->persona !== null) {
            $personaPrompt = $conversation->persona->system_prompt ?? '';

            if ($personaPrompt !== '') {
                $parts[] = $personaPrompt;
            }
        }

        if (empty($parts)) {
            $parts[] = (string) config('ai.default_system_prompt', 'You are a helpful AI assistant.');
        }

        $parts[] = $this->getTimeAwarenessPrompt(
            (string) config('app.timezone', 'America/Los_Angeles'),
        );

        return implode("\n\n", $parts);
    }

    /**
     * Load recent messages, optionally starting after the last summary's covered messages.
     *
     * @return Collection<int, Message>
     */
    private function loadRecentMessages(Conversation $conversation, ?ConversationSummary $summary): Collection
    {
        $query = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('sequence', 'asc');

        if ($summary !== null && ! empty($summary->covers_message_ids)) {
            $query->whereNotIn('id', $summary->covers_message_ids);
        }

        return $query->get();
    }

    /**
     * Select as many messages as fit within the token budget, keeping order.
     *
     * @param  Collection<int, Message>  $messages
     * @return Collection<int, Message>
     */
    private function selectMessagesWithinBudget(Collection $messages, int $tokenBudget): Collection
    {
        if ($tokenBudget <= 0) {
            return $messages->take(0);
        }

        $selected = [];
        $remaining = $tokenBudget;

        // Walk from newest to oldest so we prefer recent context
        foreach ($messages->reverse() as $message) {
            $tokens = $message->tokens_used > 0
                ? $message->tokens_used
                : $this->estimateTokens($message->content);

            if ($remaining - $tokens < 0) {
                break;
            }

            $selected[] = $message;
            $remaining -= $tokens;
        }

        // Restore chronological order
        return new Collection(array_reverse($selected));
    }

    private function getLatestSummary(Conversation $conversation): ?ConversationSummary
    {
        return ConversationSummary::where('conversation_id', $conversation->id)
            ->latest()
            ->first();
    }
}
