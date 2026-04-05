<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IntegrationDefinition;
use Illuminate\Console\Command;

class IntegrationsList extends Command
{
    protected $signature = 'integrations:list
        {--category= : Filter by category}';

    protected $description = 'List all integration definitions with connection counts';

    public function handle(): int
    {
        $query = IntegrationDefinition::withCount('userIntegrations')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name');

        $categoryOption = $this->option('category');
        $category = is_string($categoryOption) ? $categoryOption : null;

        if ($category !== null) {
            $query->where('category', $category);
        }

        $definitions = $query->get();

        if ($definitions->isEmpty()) {
            $this->warn('No integration definitions found'.($category !== null ? " for category [{$category}]" : '').'.');

            return self::SUCCESS;
        }

        /** @var array<int, array<int, string|int>> $rows */
        $rows = $definitions->map(fn (IntegrationDefinition $d) => [
            $d->name,
            $d->display_name,
            $d->category,
            $d->auth_type,
            $d->is_active ? 'yes' : 'no',
            $d->user_integrations_count,
        ])->all();

        $this->table(
            ['Name', 'Display Name', 'Category', 'Auth Type', 'Active', 'Connected Users'],
            $rows,
        );

        $this->newLine();
        $this->info("Total: {$definitions->count()} integration(s).");

        return self::SUCCESS;
    }
}
