<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ModelPullProgress;
use App\Models\AiModel;
use App\Services\AI\Providers\OllamaProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly string $modelName,
        public readonly ?string $userId = null,
    ) {
        $this->onQueue('training');
    }

    public function handle(OllamaProvider $ollamaProvider): void
    {
        Log::info('[PullModelJob] Starting model pull', [
            'model' => $this->modelName,
            'user_id' => $this->userId,
        ]);

        $generator = $ollamaProvider->pullModel($this->modelName);

        foreach ($generator as $chunk) {
            $status = (string) ($chunk['status'] ?? '');
            $completed = isset($chunk['completed']) ? (int) $chunk['completed'] : null;
            $total = isset($chunk['total']) ? (int) $chunk['total'] : null;

            $percentage = ($completed !== null && $total !== null && $total > 0)
                ? (int) round(($completed / $total) * 100)
                : 0;

            broadcast(new ModelPullProgress(
                modelName: $this->modelName,
                percentage: $percentage,
                status: $status,
            ));
        }

        AiModel::updateOrCreate(
            ['name' => $this->modelName],
            [
                'is_active' => true,
                'is_local' => true,
                'last_updated_at' => now(),
            ],
        );

        broadcast(new ModelPullProgress(
            modelName: $this->modelName,
            percentage: 100,
            status: 'success',
        ));

        Log::info('[PullModelJob] Model pull complete', [
            'model' => $this->modelName,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[PullModelJob] Job failed', [
            'model' => $this->modelName,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);

        broadcast(new ModelPullProgress(
            modelName: $this->modelName,
            percentage: 0,
            status: 'error',
            error: $exception->getMessage(),
        ));
    }
}
