<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pgvector\Laravel\HasNeighbors;

class Message extends Model
{
    use HasNeighbors, HasUlids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tokens_used',
        'finish_reason',
        'model_version',
        'sequence',
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
            'tokens_used' => 'integer',
            'sequence' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function edits(): HasMany
    {
        return $this->hasMany(MessageEdit::class);
    }
}
