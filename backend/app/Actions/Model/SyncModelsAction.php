<?php

declare(strict_types=1);

namespace App\Actions\Model;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\AI\ModelRouterService;
use Illuminate\Support\Facades\Log;

class SyncModelsAction
{
    public function __construct(
        private readonly ModelRouterService $modelRouterService,
    ) {}

    /**
     * Sync models from all active providers (or a specific one) into ai_models.
     *
     * @return array{added: int, updated: int, removed: int}
     */
    public function handle(?string $providerName = null): array
    {
        $added = 0;
        $updated = 0;
        $removed = 0;

        $query = AiProvider::query();

        if ($providerName !== null) {
            $query->where('name', $providerName);
        }

        $providers = $query->get();

        foreach ($providers as $provider) {
            try {
                $providerService = $this->modelRouterService->resolveProvider($provider->name);

                if (! $providerService->isAvailable()) {
                    continue;
                }

                $remoteModels = $providerService->listModels();
                $remoteNames = array_column($remoteModels, 'name');

                foreach ($remoteModels as $remoteModel) {
                    $modelName = (string) $remoteModel['name'];

                    $existing = AiModel::where('name', $modelName)
                        ->where('provider_id', $provider->id)
                        ->first();

                    if ($existing !== null) {
                        $existing->update([
                            'is_active' => true,
                            'ollama_digest' => $remoteModel['digest'] ?? $existing->ollama_digest,
                            'last_updated_at' => now(),
                        ]);
                        $updated++;
                    } else {
                        AiModel::create([
                            'provider_id' => $provider->id,
                            'name' => $modelName,
                            'display_name' => $modelName,
                            'ollama_model_id' => $provider->name === 'ollama' ? $modelName : null,
                            'is_active' => true,
                            'is_local' => $provider->name === 'ollama',
                            'ollama_digest' => $remoteModel['digest'] ?? null,
                            'last_updated_at' => now(),
                        ]);
                        $added++;
                    }
                }

                $deactivated = AiModel::where('provider_id', $provider->id)
                    ->where('is_active', true)
                    ->whereNotIn('name', $remoteNames)
                    ->update(['is_active' => false]);

                $removed += $deactivated;
            } catch (\Throwable $e) {
                Log::warning('[SyncModelsAction] Failed to sync provider '.$provider->name.': '.$e->getMessage());
            }
        }

        return [
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
        ];
    }
}
