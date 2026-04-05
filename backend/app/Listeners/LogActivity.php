<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ConversationCreated;
use App\Events\ConversationUpdated;
use App\Events\IntegrationConnected;
use App\Events\IntegrationDisconnected;
use App\Events\MessageCompleted;
use App\Events\MessageCreated;
use App\Events\TrainingJobStatusChanged;
use App\Models\User;

class LogActivity
{
    public function handle(mixed $event): void
    {
        $description = match (true) {
            $event instanceof MessageCreated => 'Sent message in conversation',
            $event instanceof MessageCompleted => 'AI response completed',
            $event instanceof ConversationCreated => 'Created conversation',
            $event instanceof ConversationUpdated => 'Updated conversation',
            $event instanceof TrainingJobStatusChanged => "Training job status changed to {$event->status}",
            $event instanceof IntegrationConnected => "Connected {$event->integrationName}",
            $event instanceof IntegrationDisconnected => "Disconnected {$event->integrationName}",
            default => 'Event occurred',
        };

        $userId = match (true) {
            $event instanceof MessageCreated => null,
            $event instanceof MessageCompleted => null,
            $event instanceof ConversationCreated => $event->userId,
            $event instanceof ConversationUpdated => $event->userId,
            $event instanceof TrainingJobStatusChanged => $event->userId,
            $event instanceof IntegrationConnected => $event->userId,
            $event instanceof IntegrationDisconnected => $event->userId,
            default => null,
        };

        $logger = activity();

        if ($userId !== null) {
            $user = User::find($userId);
            if ($user !== null) {
                $logger = $logger->causedBy($user);
            }
        }

        $logger->log($description);
    }
}
