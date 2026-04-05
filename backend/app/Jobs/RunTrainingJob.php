<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunTrainingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly string $trainingJobId,
    ) {}

    public function handle(): void
    {
        // Axolotl / fine-tuning implementation goes here.
    }

    public function failed(\Throwable $exception): void
    {
        // Mark the TrainingJob record as failed on queue failure.
    }
}
