<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AI\Providers\OllamaProvider;
use Illuminate\Console\Command;

class ModelsDelete extends Command
{
    protected $signature = 'models:delete
        {model : The model name to delete}';

    protected $description = 'Delete a model from Ollama';

    public function __construct(
        private readonly OllamaProvider $ollamaProvider,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $model = (string) $this->argument('model');

        if (! $this->confirm("Are you sure you want to delete [{$model}]?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        if (! $this->ollamaProvider->isAvailable()) {
            $this->error('Ollama is not available. Ensure the Ollama service is running and reachable.');

            return self::FAILURE;
        }

        try {
            $deleted = $this->ollamaProvider->deleteModel($model);
        } catch (\Throwable $e) {
            $this->error("Failed to delete [{$model}] from Ollama: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $deleted) {
            $this->error("Ollama reported failure when deleting [{$model}].");

            return self::FAILURE;
        }

        $this->softDeleteModelRecord($model);

        $this->info("Model [{$model}] deleted successfully.");

        return self::SUCCESS;
    }

    private function softDeleteModelRecord(string $modelName): void
    {
        $provider = AiProvider::where('name', 'ollama')->first();

        $query = AiModel::query();

        if ($provider !== null) {
            $query->where('provider_id', $provider->id);
        }

        $record = $query->where(function ($q) use ($modelName): void {
            $q->where('ollama_model_id', $modelName)
                ->orWhere('name', $modelName);
        })->first();

        if ($record !== null) {
            $record->delete();
        }
    }
}
