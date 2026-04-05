<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationSummary extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'conversation_id',
        'content',
        'covers_message_ids',
        'message_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'covers_message_ids' => 'array',
            'message_count' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
