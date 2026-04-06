<?php

declare(strict_types=1);

namespace App\Actions\Memory;

use App\Models\Memory;
use App\Models\MemoryConflict;
use Illuminate\Validation\ValidationException;

class ResolveMemoryConflictAction
{
    /**
     * Resolve a memory conflict with the given resolution strategy.
     *
     * Resolution options:
     *   keep_new  - soft-delete the conflicting (older) memory, mark conflict resolved
     *   keep_old  - soft-delete the new memory, mark conflict resolved
     *   merge     - combine content into the newer memory, soft-delete the older, mark resolved
     *   dismiss   - mark resolved but keep both memories intact
     *
     * @throws ValidationException
     */
    public function handle(MemoryConflict $conflict, string $resolution): void
    {
        $validResolutions = ['keep_new', 'keep_old', 'merge', 'dismiss'];

        if (!in_array($resolution, $validResolutions, true)) {
            throw ValidationException::withMessages([
                'resolution' => ['Invalid resolution. Must be one of: '.implode(', ', $validResolutions).'.'],
            ]);
        }

        $conflict->loadMissing(['memory', 'conflictingMemory']);

        /** @var Memory|null $newMemory */
        $newMemory = $conflict->memory;

        /** @var Memory|null $oldMemory */
        $oldMemory = $conflict->conflictingMemory;

        match ($resolution) {
            'keep_new' => $this->keepNew($newMemory, $oldMemory),
            'keep_old' => $this->keepOld($newMemory, $oldMemory),
            'merge'    => $this->merge($newMemory, $oldMemory),
            'dismiss'  => null,
        };

        $conflict->update([
            'resolved'   => true,
            'resolution' => $resolution,
        ]);
    }

    private function keepNew(?Memory $newMemory, ?Memory $oldMemory): void
    {
        $oldMemory?->delete();
    }

    private function keepOld(?Memory $newMemory, ?Memory $oldMemory): void
    {
        $newMemory?->delete();
    }

    private function merge(?Memory $newMemory, ?Memory $oldMemory): void
    {
        if ($newMemory === null || $oldMemory === null) {
            return;
        }

        $mergedContent = $newMemory->content.' '.$oldMemory->content;

        $newMemory->update([
            'content'    => trim($mergedContent),
            'importance' => max($newMemory->importance, $oldMemory->importance),
        ]);

        $oldMemory->delete();
    }
}
