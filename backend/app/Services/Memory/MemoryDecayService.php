<?php

declare(strict_types=1);

namespace App\Services\Memory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemoryDecayService
{
    /**
     * Decay the importance of memories that have not been accessed recently.
     *
     * Uses a single bulk UPDATE for efficiency. Importance never falls below $floor.
     *
     * @return int Number of memories updated
     */
    public function decayUnaccessed(int $daysThreshold = 30, int $decayAmount = 1, int $floor = 1): int
    {
        $threshold = now()->subDays($daysThreshold)->toDateTimeString();

        $affected = DB::affectingStatement(
            'UPDATE memories
             SET importance = GREATEST(importance - ?, ?)
             WHERE is_active = true
               AND deleted_at IS NULL
               AND (
                   last_accessed_at < ?
                   OR (last_accessed_at IS NULL AND created_at < ?)
               )',
            [$decayAmount, $floor, $threshold, $threshold],
        );

        Log::info('[MemoryDecayService] Decayed importance for '.$affected.' memories older than '.$daysThreshold.' days.');

        return $affected;
    }

    /**
     * Soft-delete memories that have decayed to minimum importance and are long inactive.
     *
     * @return int Number of memories pruned
     */
    public function pruneInactive(int $importanceThreshold = 1, int $daysInactive = 90): int
    {
        $threshold = now()->subDays($daysInactive)->toDateTimeString();

        $affected = DB::affectingStatement(
            'UPDATE memories
             SET deleted_at = NOW()
             WHERE is_active = true
               AND deleted_at IS NULL
               AND importance <= ?
               AND (
                   last_accessed_at < ?
                   OR (last_accessed_at IS NULL AND created_at < ?)
               )',
            [$importanceThreshold, $threshold, $threshold],
        );

        Log::info('[MemoryDecayService] Pruned '.$affected.' inactive memories (importance <= '.$importanceThreshold.', inactive '.$daysInactive.'+ days).');

        return $affected;
    }
}
