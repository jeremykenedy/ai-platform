<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AI\Providers\OllamaProvider;
use Illuminate\Console\Command;

class ModelsPull extends Command
{
    protected $signature = 'models:pull
        {model : The model name to pull (e.g. llama3.2:latest)}
        {--silent : Suppress progress output}';

    protected $description = 'Pull a model via Ollama';

    public function __construct(
        private readonly OllamaProvider $ollamaProvider,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $model = (string) $this->argument('model');
        $quiet = (bool) $this->option('silent');

        if (!$this->ollamaProvider->isAvailable()) {
            $this->error('Ollama is not available. Ensure the Ollama service is running and reachable.');

            return self::FAILURE;
        }

        $this->info("Pulling [{$model}]...");
        $this->newLine();

        try {
            $lastStatus = '';

            foreach ($this->ollamaProvider->pullModel($model) as $chunk) {
                if (!is_array($chunk)) {
                    continue;
                }

                $status = (string) ($chunk['status'] ?? '');

                if (!$quiet && $status !== '') {
                    if (isset($chunk['total'], $chunk['completed']) && $chunk['total'] > 0) {
                        $pct = (int) round(($chunk['completed'] / $chunk['total']) * 100);
                        $this->line("  {$status}: {$pct}%");
                    } elseif ($status !== $lastStatus) {
                        $this->line("  {$status}");
                    }

                    $lastStatus = $status;
                }
            }
        } catch (\Throwable $e) {
            $this->error("Failed to pull [{$model}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Model [{$model}] pulled successfully.");

        $this->upsertModel($model);

        return self::SUCCESS;
    }

    private function upsertModel(string $modelName): void
    {
        $provider = AiProvider::where('name', 'ollama')->first();

        if ($provider === null) {
            return;
        }

        $modelDetails = [];

        try {
            $modelDetails = $this->ollamaProvider->showModel($modelName);
        } catch (\Throwable) {
            // Non-fatal; proceed with minimal data.
        }

        AiModel::updateOrCreate(
            ['name' => $modelName, 'provider_id' => $provider->id],
            [
                'display_name'    => $modelName,
                'ollama_model_id' => $modelName,
                'is_active'       => true,
                'is_local'        => true,
                'ollama_digest'   => $modelDetails['digest'] ?? null,
                'last_updated_at' => now(),
            ],
        );
    }
}
