<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $messageId,
        public int $tokensUsed,
        public string $finishReason,
    ) {}

    /**
     * @return Channel|array<int, Channel>
     */
    public function broadcastOn(): Channel|array
    {
        return new PrivateChannel("conversation.{$this->conversationId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'tokens_used' => $this->tokensUsed,
            'finish_reason' => $this->finishReason,
        ];
    }

    public function broadcastAs(): string
    {
        return 'StreamCompleted';
    }
}
