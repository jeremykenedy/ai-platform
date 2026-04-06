<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Models\IntegrationDefinition;
use App\Models\IntegrationToolCall;
use App\Models\User;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Support\Facades\Log;

class ExecuteIntegrationToolAction
{
    public function __construct(
        private readonly IntegrationManager $integrationManager,
    ) {
    }

    /**
     * Execute an integration tool call, log it, and return the result.
     *
     * @param array<string, mixed> $params
     */
    public function handle(
        User $user,
        string $integrationName,
        string $toolName,
        array $params,
        ?string $conversationId = null,
        ?string $messageId = null,
    ): mixed {
        $definition = IntegrationDefinition::where('name', $integrationName)->first();

        $startMs = (int) round(microtime(true) * 1000);
        $result = null;
        $status = 'success';
        $errorMessage = null;

        try {
            $result = $this->integrationManager->executeTool($integrationName, $toolName, $params, $user);
        } catch (\Throwable $e) {
            $status = 'error';
            $errorMessage = $e->getMessage();
            Log::warning('[ExecuteIntegrationToolAction] Tool execution failed', [
                'integration' => $integrationName,
                'tool'        => $toolName,
                'error'       => $e->getMessage(),
            ]);
        }

        $durationMs = (int) round(microtime(true) * 1000) - $startMs;

        IntegrationToolCall::create([
            'user_id'         => $user->id,
            'conversation_id' => $conversationId,
            'message_id'      => $messageId,
            'integration_id'  => $definition?->id,
            'tool_name'       => $toolName,
            'input'           => $params,
            'output'          => is_array($result) ? $result : ['result' => $result],
            'status'          => $status,
            'duration_ms'     => $durationMs,
            'error_message'   => $errorMessage,
        ]);

        if ($status === 'error') {
            throw new \RuntimeException($errorMessage ?? 'Integration tool execution failed.');
        }

        return $result;
    }
}
