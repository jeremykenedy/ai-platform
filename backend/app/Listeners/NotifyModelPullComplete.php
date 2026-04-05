<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ModelPullProgress;
use Illuminate\Support\Facades\Log;

class NotifyModelPullComplete
{
    public function handle(ModelPullProgress $event): void
    {
        if ($event->error !== null) {
            Log::error("Model {$event->modelName} pull failed: {$event->error}");

            return;
        }

        if ($event->percentage === 100) {
            Log::info("Model {$event->modelName} pull completed");
        }
    }
}
