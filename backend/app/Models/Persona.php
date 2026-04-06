<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $user_id
 * @property string      $name
 * @property string|null $description
 * @property string      $system_prompt
 * @property string|null $model_name
 * @property float       $temperature
 * @property float       $top_p
 * @property int         $top_k
 * @property float       $repeat_penalty
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Persona extends Model
{
    /** @use HasFactory<PersonaFactory> */
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'system_prompt',
        'model_name',
        'temperature',
        'top_p',
        'top_k',
        'repeat_penalty',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature'    => 'float',
            'top_p'          => 'float',
            'top_k'          => 'integer',
            'repeat_penalty' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
