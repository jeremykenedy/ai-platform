<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\Providers\OllamaProvider;
use Illuminate\Console\Command;

class ModelsRunning extends Command
{
    protected $signature = 'models:running';

    protected $description = 'Show currently loaded Ollama models';

    public function __construct(
        private readonly OllamaProvider $ollamaProvider,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->ollamaProvider->isAvailable()) {
            $this->error('Ollama is not available. Ensure the Ollama service is running and reachable.');

            return self::FAILURE;
        }

        try {
            $running = $this->ollamaProvider->getRunningModels();
        } catch (\Throwable $e) {
            $this->error("Failed to retrieve running models: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (empty($running)) {
            $this->info('No models currently loaded.');

            return self::SUCCESS;
        }

        $rows = array_map(function (array $model): array {
            $sizeBytes = (int) ($model['size'] ?? 0);
            $vramBytes = (int) ($model['size_vram'] ?? 0);

            $size = $sizeBytes > 0 ? $this->formatBytes($sizeBytes) : '-';
            $vram = $vramBytes > 0 ? $this->formatBytes($vramBytes) : '-';

            $until = '-';

            if (isset($model['expires_at']) && $model['expires_at'] !== '') {
                try {
                    $expires = new \DateTimeImmutable((string) $model['expires_at']);
                    $until = $expires->format('Y-m-d H:i:s');
                } catch (\Throwable) {
                    $until = (string) $model['expires_at'];
                }
            }

            return [
                $model['name'] ?? '-',
                $size,
                $vram,
                $until,
            ];
        }, $running);

        $this->table(['Model', 'Size', 'VRAM Used', 'Until'], $rows);
        $this->newLine();
        $this->info(count($running).' model(s) currently loaded.');

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 2).' GB';
        }

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }

        return round($bytes / 1_024, 2).' KB';
    }
}
