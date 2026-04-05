<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\TrainingJobStatusChanged;
use App\Models\TrainingJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunTrainingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public int $maxExceptions = 1;

    public function __construct(
        public readonly string $trainingJobId,
    ) {
        $this->onQueue('training');
    }

    public function handle(): void
    {
        /** @var TrainingJob $job */
        $job = TrainingJob::with(['dataset', 'baseModel'])->findOrFail($this->trainingJobId);

        $job->update([
            'status' => 'running',
            'started_at' => now(),
            'progress' => 0,
        ]);

        broadcast(new TrainingJobStatusChanged(
            userId: (string) $job->user_id,
            jobId: $this->trainingJobId,
            status: 'running',
            progress: 0,
        ));

        Log::info('[RunTrainingJob] Training started', [
            'training_job_id' => $this->trainingJobId,
            'base_model' => $job->baseModel?->name,
            'dataset' => $job->dataset?->id,
            'output_model_name' => $job->output_model_name,
        ]);

        // Simulate training steps (replace with real Axolotl API calls in production)
        $steps = 10;

        for ($step = 1; $step <= $steps; $step++) {
            $progress = (int) round(($step / $steps) * 100);

            $job->update([
                'progress' => $progress,
                'log_output' => ($job->log_output ?? '')."Step {$step}/{$steps} completed.\n",
            ]);

            broadcast(new TrainingJobStatusChanged(
                userId: (string) $job->user_id,
                jobId: $this->trainingJobId,
                status: 'running',
                progress: $progress,
            ));

            Log::info('[RunTrainingJob] Step completed', [
                'training_job_id' => $this->trainingJobId,
                'step' => $step,
                'progress' => $progress,
            ]);
        }

        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress' => 100,
        ]);

        broadcast(new TrainingJobStatusChanged(
            userId: (string) $job->user_id,
            jobId: $this->trainingJobId,
            status: 'completed',
            progress: 100,
        ));

        Log::info('[RunTrainingJob] Training completed', [
            'training_job_id' => $this->trainingJobId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[RunTrainingJob] Job failed', [
            'training_job_id' => $this->trainingJobId,
            'error' => $exception->getMessage(),
        ]);

        $job = TrainingJob::find($this->trainingJobId);

        if ($job !== null) {
            $job->update([
                'status' => 'failed',
                'log_output' => ($job->log_output ?? '')."Error: {$exception->getMessage()}\n",
            ]);

            broadcast(new TrainingJobStatusChanged(
                userId: (string) $job->user_id,
                jobId: $this->trainingJobId,
                status: 'failed',
                progress: (int) $job->progress,
            ));
        }
    }
}
