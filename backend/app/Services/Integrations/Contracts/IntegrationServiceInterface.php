<?php

declare(strict_types=1);

namespace App\Services\Integrations\Contracts;

use App\Models\User;

interface IntegrationServiceInterface
{
    /**
     * Returns the list of tool definitions this integration exposes.
     *
     * Each entry is a map with keys:
     *   'name'        => string  (unique tool identifier, snake_case)
     *   'description' => string  (human-readable description for the model)
     *   'parameters'  => array   (JSON Schema object describing accepted parameters)
     *
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function getTools(): array;

    /**
     * Execute a named tool call and return its result.
     *
     * @param  array<string, mixed>  $params
     */
    public function executeTool(string $toolName, array $params, User $user): mixed;

    /**
     * Return true if the given user has valid, enabled credentials stored.
     */
    public function isConnected(User $user): bool;

    /**
     * Return the OAuth authorization URL to initiate the OAuth flow, or null
     * for integrations that use API key auth (no redirect needed).
     */
    public function getAuthUrl(User $user): ?string;

    /**
     * Handle the OAuth callback and persist the returned tokens for the user.
     *
     * @param  array<string, mixed>  $params
     */
    public function handleCallback(User $user, array $params): void;

    /**
     * Disable and clear stored credentials for the user.
     */
    public function disconnect(User $user): void;

    /**
     * Verify that the stored credentials are still valid by issuing a
     * lightweight API call. Returns true on success, false on failure.
     */
    public function testConnection(User $user): bool;
}
