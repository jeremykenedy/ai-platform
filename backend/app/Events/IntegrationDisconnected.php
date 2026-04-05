<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntegrationDisconnected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $userId,
        public string $integrationName,
    ) {}
}
