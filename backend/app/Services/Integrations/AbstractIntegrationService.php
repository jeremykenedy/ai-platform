<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Models\IntegrationDefinition;
use App\Models\IntegrationToolCall;
use App\Models\User;
use App\Models\UserIntegration;
use App\Services\Integrations\Contracts\IntegrationServiceInterface;
use Illuminate\Support\Facades\Log;

abstract class AbstractIntegrationService implements IntegrationServiceInterface
{
    /**
     * The snake_case name that matches the 'name' column in integration_definitions.
     * Concrete services must set this property (e.g. 'google_calendar').
     */
    protected string $integrationName = '';

    /**
     * Returns true when the user has an enabled record with stored credentials
     * or an OAuth token in the user_integrations table.
     */
    public function isConnected(User $user): bool
    {
        $integration = $this->getUserIntegration($user);

        if ($integration === null || !$integration->is_enabled) {
            return false;
        }

        return $integration->credentials !== null || $integration->oauth_token !== null;
    }

    /**
     * Disable the integration and wipe all stored credential data for the user.
     */
    public function disconnect(User $user): void
    {
        $integration = $this->getUserIntegration($user);

        if ($integration === null) {
            return;
        }

        $integration->update([
            'is_enabled'          => false,
            'credentials'         => null,
            'oauth_token'         => null,
            'oauth_refresh_token' => null,
            'oauth_expires_at'    => null,
            'scopes_granted'      => null,
        ]);
    }

    /**
     * Verify credentials by attempting a cheap tool call.
     * Returns true on success, false on any exception.
     */
    public function testConnection(User $user): bool
    {
        $tools = $this->getTools();

        if ($tools === []) {
            return false;
        }

        try {
            $this->executeTool($tools[0]['name'], [], $user);

            return true;
        } catch (\Throwable $e) {
            Log::warning(sprintf(
                '[Integration:%s] testConnection failed for user %s: %s',
                $this->integrationName,
                $user->getKey(),
                $e->getMessage(),
            ));

            return false;
        }
    }

    /**
     * API-key integrations have no OAuth redirect.
     * Override in OAuth-based subclasses to return the authorization URL.
     */
    public function getAuthUrl(User $user): ?string
    {
        return null;
    }

    /**
     * No-op default for integrations that do not use OAuth callbacks.
     * Override in OAuth-based subclasses.
     *
     * @param array<string, mixed> $params
     */
    public function handleCallback(User $user, array $params): void
    {
    }

    /**
     * Find the user's UserIntegration record for this integration, or null.
     */
    protected function getUserIntegration(User $user): ?UserIntegration
    {
        $definition = $this->getDefinition();

        /** @var UserIntegration|null */
        return UserIntegration::query()
            ->where('user_id', $user->getKey())
            ->where('integration_id', $definition->getKey())
            ->first();
    }

    /**
     * Retrieve and decrypt stored credentials for the user.
     *
     * @return array<string, mixed>|null
     */
    protected function getCredentials(User $user): ?array
    {
        $integration = $this->getUserIntegration($user);

        if ($integration === null) {
            return null;
        }

        /** @var array<string, mixed>|null */
        $credentials = $integration->credentials;

        return $credentials;
    }

    /**
     * Load the IntegrationDefinition model for this service by name.
     */
    protected function getDefinition(): IntegrationDefinition
    {
        /** @var IntegrationDefinition */
        return IntegrationDefinition::query()
            ->where('name', $this->integrationName)
            ->firstOrFail();
    }

    /**
     * Persist a record of a tool call to the integration_tool_calls table.
     *
     * @param array<string, mixed> $input
     */
    protected function logToolCall(
        User $user,
        string $conversationId,
        string $messageId,
        string $toolName,
        array $input,
        mixed $output,
        string $status,
        ?int $durationMs = null,
        ?string $error = null,
    ): void {
        try {
            $definition = $this->getDefinition();

            IntegrationToolCall::create([
                'user_id'         => $user->getKey(),
                'conversation_id' => $conversationId,
                'message_id'      => $messageId,
                'integration_id'  => $definition->getKey(),
                'tool_name'       => $toolName,
                'input'           => $input,
                'output'          => is_array($output) ? $output : ['result' => $output],
                'status'          => $status,
                'duration_ms'     => $durationMs,
                'error_message'   => $error,
            ]);
        } catch (\Throwable $e) {
            Log::error(sprintf(
                '[Integration:%s] Failed to log tool call "%s": %s',
                $this->integrationName,
                $toolName,
                $e->getMessage(),
            ));
        }
    }
}
