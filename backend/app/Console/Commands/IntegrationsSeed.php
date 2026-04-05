<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IntegrationDefinition;
use Database\Seeders\IntegrationDefinitionsSeeder;
use Illuminate\Console\Command;

class IntegrationsSeed extends Command
{
    protected $signature = 'integrations:seed
        {--force : Overwrite existing definitions}';

    protected $description = 'Seed integration definitions table';

    public function handle(): int
    {
        $this->info('Seeding integration definitions...');

        (new IntegrationDefinitionsSeeder)->run();

        $count = IntegrationDefinition::count();

        $this->info('Integration definitions seeded successfully.');
        $this->line("Total definitions: {$count}");

        return self::SUCCESS;
    }
}
