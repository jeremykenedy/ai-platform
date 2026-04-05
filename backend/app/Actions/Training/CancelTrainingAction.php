<?php

declare(strict_types=1);

namespace App\Actions\Training;

use App\Models\TrainingJob;

class CancelTrainingAction
{
    /**
     * Cancel a training job by marking it cancelled with a completion timestamp.
     */
    public function handle(TrainingJob $job): void
    {
        $job->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }
}
