<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'extracted_text',
        'extraction_status',
        'token_estimate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'token_estimate' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
