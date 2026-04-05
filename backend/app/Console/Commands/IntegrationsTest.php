<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Console\Command;

class IntegrationsTest extends Command
{
    protected $signature = 'integrations:test
        {user : User ID or email}
        {integration : Integration name}';

    protected $description = 'Test a user integration connection';

    public function __construct(
        private readonly IntegrationManager $integrationManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userArgument = $this->argument('user');
        $userIdentifier = is_string($userArgument) ? $userArgument : (string) $userArgument;

        $user = str_contains($userIdentifier, '@')
            ? User::where('email', $userIdentifier)->first()
            : User::find($userIdentifier);

        if ($user === null) {
            $this->error("User not found: {$userIdentifier}");

            return self::FAILURE;
        }

        $integrationName = $this->argument('integration');
        $integrationName = is_string($integrationName) ? $integrationName : (string) $integrationName;

        try {
            $service = $this->integrationManager->resolve($integrationName);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("User:        {$user->name} <{$user->email}>");
        $this->line("Integration: {$integrationName}");
        $this->newLine();

        $isConnected = $service->isConnected($user);

        if (! $isConnected) {
            $this->warn('Status: not connected');

            return self::FAILURE;
        }

        $this->info('Status: connected');
        $this->line('Testing connection...');

        $start = hrtime(true);
        $success = $service->testConnection($user);
        $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

        if ($success) {
            $this->info("Connection test passed ({$latencyMs}ms).");

            return self::SUCCESS;
        }

        $this->error("Connection test failed ({$latencyMs}ms).");

        return self::FAILURE;
    }
}
