<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pgvector\Laravel\HasNeighbors;

class Memory extends Model
{
    use HasNeighbors, HasUlids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'content',
        'source_conversation_id',
        'source_message_id',
        'category',
        'importance',
        'last_accessed_at',
        'access_count',
        'is_active',
        'embedding',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'embedding',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'importance' => 'integer',
            'access_count' => 'integer',
            'last_accessed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'source_conversation_id');
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'source_message_id');
    }

    /**
     * @param  Builder<Memory>  $query
     * @return Builder<Memory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Memory>  $query
     * @return Builder<Memory>
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * @param  Builder<Memory>  $query
     * @return Builder<Memory>
     */
    public function scopeByImportance(Builder $query, int $importance): Builder
    {
        return $query->where('importance', '>=', $importance);
    }
}
