<?php

declare(strict_types=1);

namespace App\Actions\Training;

use App\Jobs\RunTrainingJob;
use App\Models\TrainingJob;

class StartTrainingAction
{
    /**
     * Mark a training job as running and dispatch it to the queue.
     */
    public function handle(TrainingJob $job): void
    {
        $job->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        RunTrainingJob::dispatch($job->id);
    }
}
