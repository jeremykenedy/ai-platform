<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiModel;
use Illuminate\Console\Command;

class ModelsList extends Command
{
    protected $signature = 'models:list
        {--provider= : Filter by provider name}
        {--local : Show only local models}
        {--remote : Show only remote models}
        {--active : Show only active models}
        {--capability= : Filter by capability}';

    protected $description = 'List all registered AI models';

    public function handle(): int
    {
        $query = AiModel::with('provider')->withTrashed(false);

        $providerOption = $this->option('provider');

        if (is_string($providerOption) && $providerOption !== '') {
            $query->whereHas('provider', fn ($q) => $q->where('name', $providerOption));
        }

        if ($this->option('local')) {
            $query->where('is_local', true);
        } elseif ($this->option('remote')) {
            $query->where('is_local', false);
        }

        if ($this->option('active')) {
            $query->where('is_active', true);
        }

        $capabilityOption = $this->option('capability');

        if (is_string($capabilityOption) && $capabilityOption !== '') {
            $query->whereJsonContains('capabilities', $capabilityOption);
        }

        $models = $query->orderBy('created_at', 'desc')->get();

        if ($models->isEmpty()) {
            $this->info('No models found matching the given filters.');

            return self::SUCCESS;
        }

        $rows = $models->map(function (AiModel $model): array {
            $capabilities = (array) ($model->capabilities ?? []);

            $capMap = [
                'chat'       => 'chat',
                'vision'     => 'vis',
                'code'       => 'code',
                'reasoning'  => 'rsn',
                'image'      => 'img',
                'audio'      => 'aud',
                'embeddings' => 'emb',
                'streaming'  => 'str',
            ];

            $capCodes = [];

            foreach ($capMap as $full => $short) {
                if (in_array($full, $capabilities, true)) {
                    $capCodes[] = $short;
                }
            }

            $params = $model->parameter_count !== null
                ? number_format((float) $model->parameter_count / 1_000_000_000, 1).'B'
                : '-';

            $context = $model->context_window !== null
                ? number_format($model->context_window)
                : '-';

            return [
                $model->provider?->name ?? '-',
                $model->name,
                $model->display_name ?? '-',
                $params,
                $context,
                implode(', ', $capCodes) ?: '-',
                $model->is_active ? 'yes' : 'no',
                $model->is_local ? 'yes' : 'no',
            ];
        })->all();

        $this->table(
            ['Provider', 'Model', 'Display Name', 'Params', 'Context', 'Capabilities', 'Active', 'Local'],
            $rows,
        );

        $this->newLine();
        $this->info("Total: {$models->count()} model(s).");

        return self::SUCCESS;
    }
}
