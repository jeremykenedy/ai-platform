<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\ConversationSummary;
use App\Services\Memory\ConversationSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SummarizeConversationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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

    public function handle(ConversationSummaryService $summaryService): void
    {
        /** @var Conversation $conversation */
        $conversation = Conversation::findOrFail($this->conversationId);

        $summary = $summaryService->summarize($conversation);

        if ($summary instanceof ConversationSummary) {
            Log::info('[SummarizeConversationJob] Summary created', [
                'conversation_id' => $this->conversationId,
                'summary_id'      => (string) $summary->id,
                'message_count'   => $summary->message_count,
            ]);
        } else {
            Log::info('[SummarizeConversationJob] No summary created (too few messages)', [
                'conversation_id' => $this->conversationId,
            ]);
        }
    }
}
