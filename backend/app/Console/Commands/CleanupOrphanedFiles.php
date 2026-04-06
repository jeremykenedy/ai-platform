<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MessageAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOrphanedFiles extends Command
{
    protected $signature = 'files:cleanup
        {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Remove orphaned files from storage not referenced by any attachment';

    public function handle(): int
    {
        $knownPaths = MessageAttachment::pluck('path')->filter()->flip()->all();

        /** @var array<int, string> $storageFiles */
        $storageFiles = Storage::disk('s3')->allFiles();

        $orphaned = array_values(array_filter(
            $storageFiles,
            fn (string $path) => !isset($knownPaths[$path]),
        ));

        $count = count($orphaned);

        if ($this->option('dry-run')) {
            if ($count === 0) {
                $this->info('[dry-run] No orphaned files found.');

                return self::SUCCESS;
            }

            $this->warn("[dry-run] Found {$count} orphaned file(s):");

            foreach ($orphaned as $path) {
                $this->line("  {$path}");
            }

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No orphaned files found.');

            return self::SUCCESS;
        }

        foreach ($orphaned as $path) {
            Storage::disk('s3')->delete($path);
        }

        $this->info("Cleaned up {$count} orphaned file(s).");

        return self::SUCCESS;
    }
}
