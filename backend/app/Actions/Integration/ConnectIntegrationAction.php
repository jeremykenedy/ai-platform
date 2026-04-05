<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Models\IntegrationDefinition;
use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Validation\ValidationException;

class ConnectIntegrationAction
{
    public function __construct(
        private readonly IntegrationManager $integrationManager,
    ) {}

    /**
     * Connect or reconnect the named integration for the user with the provided credentials.
     *
     * @param  array<string, mixed>  $credentials
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $integrationName, array $credentials): UserIntegration
    {
        $definition = IntegrationDefinition::where('name', $integrationName)
            ->where('is_active', true)
            ->first();

        if ($definition === null) {
            throw ValidationException::withMessages([
                'integration' => ["Integration '{$integrationName}' not found or is not active."],
            ]);
        }

        /** @var UserIntegration $integration */
        $integration = UserIntegration::updateOrCreate(
            [
                'user_id' => $user->id,
                'integration_id' => $definition->id,
            ],
            [
                'credentials' => $credentials,
                'is_enabled' => true,
                'last_error' => null,
            ],
        );

        $service = $this->integrationManager->resolve($integrationName);
        $service->testConnection($user);

        return $integration->fresh() ?? $integration;
    }
}
