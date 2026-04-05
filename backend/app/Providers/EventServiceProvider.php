<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\ConversationCreated;
use App\Events\ConversationUpdated;
use App\Events\IntegrationConnected;
use App\Events\IntegrationDisconnected;
use App\Events\MessageCompleted;
use App\Events\MessageCreated;
use App\Events\ModelPullProgress;
use App\Events\TrainingJobStatusChanged;
use App\Listeners\CheckConversationSummary;
use App\Listeners\DispatchMemoryExtraction;
use App\Listeners\LogActivity;
use App\Listeners\NotifyModelPullComplete;
use App\Listeners\UpdateUserLastActive;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        MessageCreated::class => [
            UpdateUserLastActive::class,
            LogActivity::class,
        ],
        MessageCompleted::class => [
            DispatchMemoryExtraction::class,
            CheckConversationSummary::class,
            LogActivity::class,
        ],
        ConversationCreated::class => [
            LogActivity::class,
        ],
        ConversationUpdated::class => [
            LogActivity::class,
        ],
        TrainingJobStatusChanged::class => [
            LogActivity::class,
        ],
        ModelPullProgress::class => [
            NotifyModelPullComplete::class,
        ],
        IntegrationConnected::class => [
            LogActivity::class,
        ],
        IntegrationDisconnected::class => [
            LogActivity::class,
        ],
    ];
}
