<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune
        {--days=90 : Remove entries older than this many days}';

    protected $description = 'Prune old activity log entries';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = is_numeric($daysOption) ? (int) $daysOption : 90;

        $count = Activity::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Pruned {$count} activity log entry/entries older than {$days} day(s).");

        return self::SUCCESS;
    }
}
