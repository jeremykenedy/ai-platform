<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IntegrationConnected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $userId,
        public string $integrationName,
    ) {
    }
}
