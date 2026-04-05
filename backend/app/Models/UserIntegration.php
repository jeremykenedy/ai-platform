<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserIntegration extends Model
{
    use HasUlids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'integration_id',
        'is_enabled',
        'credentials',
        'oauth_token',
        'oauth_refresh_token',
        'oauth_expires_at',
        'scopes_granted',
        'last_used_at',
        'last_error',
        'metadata',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'credentials',
        'oauth_token',
        'oauth_refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted',
            'oauth_token' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_expires_at' => 'datetime',
            'scopes_granted' => 'array',
            'last_used_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(IntegrationDefinition::class, 'integration_id');
    }
}
