<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $userId,
        public string $conversationId,
        public ?string $title,
    ) {
    }

    /**
     * @return Channel|array<int, Channel>
     */
    public function broadcastOn(): Channel|array
    {
        return new PrivateChannel("user.{$this->userId}");
    }
}
