<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasUlids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'project_id',
        'persona_id',
        'title',
        'model_name',
        'context_window_used',
        'enabled_integrations',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context_window_used' => 'integer',
            'enabled_integrations' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(ConversationSummary::class);
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeForUser($query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<Conversation>  $query
     * @return Builder<Conversation>
     */
    public function scopeRecent($query): Builder
    {
        return $query->orderBy('updated_at', 'desc');
    }
}
