<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Memory;
use App\Services\Memory\MemoryDecayService;
use Illuminate\Console\Command;

class MemoryDecay extends Command
{
    protected $signature = 'memory:decay
        {--days=30 : Days threshold for decay}
        {--amount=1 : Decay amount}
        {--floor=1 : Minimum importance}
        {--dry-run : Show what would be affected without changing}';

    protected $description = 'Decay importance of unaccessed memories';

    public function __construct(
        private readonly MemoryDecayService $memoryDecayService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $amountOption = $this->option('amount');
        $floorOption = $this->option('floor');

        $days = is_numeric($daysOption) ? (int) $daysOption : 30;
        $amount = is_numeric($amountOption) ? (int) $amountOption : 1;
        $floor = is_numeric($floorOption) ? (int) $floorOption : 1;

        if ($this->option('dry-run')) {
            $threshold = now()->subDays($days)->toDateTimeString();

            $count = Memory::where('is_active', true)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($threshold): void {
                    $query->where('last_accessed_at', '<', $threshold)
                        ->orWhere(function ($q) use ($threshold): void {
                            $q->whereNull('last_accessed_at')
                                ->where('created_at', '<', $threshold);
                        });
                })
                ->count();

            $this->info("[dry-run] {$count} memory/memories would be decayed (threshold: {$days} days, amount: {$amount}, floor: {$floor}).");

            return self::SUCCESS;
        }

        $decayed = $this->memoryDecayService->decayUnaccessed($days, $amount, $floor);
        $this->info("Decayed {$decayed} memory/memories.");

        $pruned = $this->memoryDecayService->pruneInactive();
        $this->info("Pruned {$pruned} inactive memory/memories.");

        return self::SUCCESS;
    }
}
