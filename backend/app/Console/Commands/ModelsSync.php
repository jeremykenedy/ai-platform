<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Model\SyncModelsAction;
use App\Models\AiProvider;
use Illuminate\Console\Command;

class ModelsSync extends Command
{
    protected $signature = 'models:sync
        {--provider= : Sync only a specific provider}
        {--force : Force sync even if recently synced}';

    protected $description = 'Sync available models from all configured AI providers';

    public function __construct(
        private readonly SyncModelsAction $syncModelsAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $providerOption = $this->option('provider');
        $providerName = is_string($providerOption) ? $providerOption : null;

        if ($providerName !== null) {
            $exists = AiProvider::where('name', $providerName)->exists();

            if (! $exists) {
                $this->error("Provider [{$providerName}] not found.");

                return self::FAILURE;
            }

            $providers = [$providerName];
        } else {
            $providers = AiProvider::pluck('name')->all();
        }

        $this->info('Syncing models from '.count($providers).' provider(s)...');
        $this->newLine();

        $tableRows = [];
        $totalAdded = 0;
        $totalUpdated = 0;
        $totalRemoved = 0;

        foreach ($providers as $provider) {
            $result = ['added' => 0, 'updated' => 0, 'removed' => 0];

            $this->components->task("Syncing [{$provider}]", function () use ($provider, &$result): void {
                $result = $this->syncModelsAction->handle($provider);
            });

            $tableRows[] = [
                $provider,
                $result['added'],
                $result['updated'],
                $result['removed'],
            ];

            $totalAdded += $result['added'];
            $totalUpdated += $result['updated'];
            $totalRemoved += $result['removed'];
        }

        $this->newLine();
        $this->table(['Provider', 'Added', 'Updated', 'Removed'], $tableRows);
        $this->newLine();
        $this->info("Total: {$totalAdded} added, {$totalUpdated} updated, {$totalRemoved} removed.");

        return self::SUCCESS;
    }
}
