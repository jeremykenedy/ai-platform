<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageStreamChunk implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $token,
        public int $sequence,
    ) {
    }

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
            'token'    => $this->token,
            'sequence' => $this->sequence,
        ];
    }

    public function broadcastAs(): string
    {
        return 'TokenReceived';
    }
}
