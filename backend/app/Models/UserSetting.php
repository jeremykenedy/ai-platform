<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $user_id
 * @property string|null $default_model_id
 * @property string|null $default_persona_id
 * @property string      $theme
 * @property int         $font_size
 * @property bool        $send_on_enter
 * @property bool        $show_token_counts
 * @property bool        $memory_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AiModel|null $defaultModel
 */
class UserSetting extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'default_model_id',
        'default_persona_id',
        'theme',
        'font_size',
        'send_on_enter',
        'show_token_counts',
        'memory_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'send_on_enter'     => 'boolean',
            'show_token_counts' => 'boolean',
            'memory_enabled'    => 'boolean',
            'font_size'         => 'integer',
        ];
    }

    /** @return BelongsTo<User, UserSetting> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AiModel, UserSetting> */
    public function defaultModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'default_model_id');
    }

    public function defaultPersona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'default_persona_id');
    }
}
