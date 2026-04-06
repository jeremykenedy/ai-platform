<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Pgvector\Laravel\HasNeighbors;

/**
 * @property string      $id
 * @property string      $conversation_id
 * @property string      $role
 * @property string      $content
 * @property int|null    $tokens_used
 * @property string|null $finish_reason
 * @property string|null $model_version
 * @property int|null    $sequence
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Conversation $conversation
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;
    use HasNeighbors;
    use HasUlids;
    use SoftDeletes;

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
            'sequence'    => 'integer',
        ];
    }

    /** @return BelongsTo<Conversation, Message> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return HasMany<MessageAttachment, Message> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /** @return HasMany<MessageEdit, Message> */
    public function edits(): HasMany
    {
        return $this->hasMany(MessageEdit::class);
    }
}
