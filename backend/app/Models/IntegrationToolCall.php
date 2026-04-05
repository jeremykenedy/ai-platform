<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationToolCall extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'conversation_id',
        'message_id',
        'integration_id',
        'tool_name',
        'input',
        'output',
        'status',
        'duration_ms',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(IntegrationDefinition::class, 'integration_id');
    }
}
