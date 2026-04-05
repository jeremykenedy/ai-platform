<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MessageCompleted;
use App\Jobs\ExtractMemoriesJob;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchMemoryExtraction implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(MessageCompleted $event): void
    {
        $conversation = Conversation::find($event->conversationId);

        if ($conversation === null) {
            return;
        }

        $messageCount = $conversation->messages()->count();

        if ($messageCount >= 5 && $messageCount % 5 === 0) {
            ExtractMemoriesJob::dispatch($event->conversationId);
        }
    }
}
