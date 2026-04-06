<?php

declare(strict_types=1);

namespace App\Actions\Model;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AI\Providers\OllamaProvider;

class PullModelAction
{
    public function __construct(
        private readonly OllamaProvider $ollamaProvider,
    ) {
    }

    /**
     * Pull a model from the Ollama registry, yielding progress updates.
     * After the pull completes, the model is synced to the ai_models table.
     */
    public function handle(string $modelName): \Generator
    {
        foreach ($this->ollamaProvider->pullModel($modelName) as $chunk) {
            yield $chunk;
        }

        $this->syncModelToDatabase($modelName);
    }

    private function syncModelToDatabase(string $modelName): void
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
