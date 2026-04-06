<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $messageId,
        public string $role,
        public string $content,
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
            'message_id' => $this->messageId,
            'role'       => $this->role,
            'content'    => $this->content,
        ];
    }
}
