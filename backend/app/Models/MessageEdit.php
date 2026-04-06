<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $message_id
 * @property string      $original_content
 * @property Carbon      $edited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MessageEdit extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'original_content',
        'edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
