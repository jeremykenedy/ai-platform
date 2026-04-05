<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Models\User;
use App\Services\Integrations\IntegrationManager;

class DisconnectIntegrationAction
{
    public function __construct(
        private readonly IntegrationManager $integrationManager,
    ) {}

    /**
     * Disconnect and clear credentials for the named integration.
     */
    public function handle(User $user, string $integrationName): void
    {
        $service = $this->integrationManager->resolve($integrationName);
        $service->disconnect($user);
    }
}
