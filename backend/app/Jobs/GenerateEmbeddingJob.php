<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Memory;
use App\Models\Message;
use App\Services\AI\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var int[] */
    public array $backoff = [2, 5, 10];

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $modelId,
    ) {
        $this->onQueue('default');
    }

    public function handle(EmbeddingService $embeddingService): void
    {
        if ($this->modelClass === Message::class) {
            /** @var Message $instance */
            $instance = Message::findOrFail($this->modelId);
            $embeddingService->storeMessageEmbedding($instance);

            return;
        }

        if ($this->modelClass === Memory::class) {
            /** @var Memory $instance */
            $instance = Memory::findOrFail($this->modelId);
            $embeddingService->storeMemoryEmbedding($instance);

            return;
        }

        Log::warning('[GenerateEmbeddingJob] Unsupported model class', [
            'model_class' => $this->modelClass,
            'model_id' => $this->modelId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[GenerateEmbeddingJob] Job failed', [
            'model_class' => $this->modelClass,
            'model_id' => $this->modelId,
            'error' => $exception->getMessage(),
        ]);
    }
}
