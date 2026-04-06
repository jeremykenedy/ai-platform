<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $memory_id
 * @property string $conflicts_with
 * @property bool $resolved
 * @property string|null $resolution
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MemoryConflict extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'memory_id',
        'conflicts_with',
        'resolved',
        'resolution',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class, 'memory_id');
    }

    public function conflictingMemory(): BelongsTo
    {
        return $this->belongsTo(Memory::class, 'conflicts_with');
    }

    /**
     * @param  Builder<MemoryConflict>  $query
     * @return Builder<MemoryConflict>
     */
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('resolved', false);
    }
}
