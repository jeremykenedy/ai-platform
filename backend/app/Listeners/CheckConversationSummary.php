<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessageCompleted;
use App\Jobs\SummarizeConversationJob;
use App\Models\Conversation;
use App\Services\Memory\ConversationSummaryService;
use Illuminate\Contracts\Queue\ShouldQueue;

class CheckConversationSummary implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(MessageCompleted $event): void
    {
        $conversation = Conversation::find($event->conversationId);

        if ($conversation === null) {
            return;
        }

        $service = app(ConversationSummaryService::class);

        if ($service->shouldSummarize($conversation)) {
            SummarizeConversationJob::dispatch($event->conversationId);
        }
    }
}
