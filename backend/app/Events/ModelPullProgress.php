<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModelPullProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $modelName,
        public int $percentage,
        public string $status,
        public ?string $error = null,
    ) {}

    /**
     * @return Channel|array<int, Channel>
     */
    public function broadcastOn(): Channel|array
    {
        return new PrivateChannel('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'model' => $this->modelName,
            'percentage' => $this->percentage,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }
}
