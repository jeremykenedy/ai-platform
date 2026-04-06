<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string            $id
 * @property string            $user_id
 * @property string            $integration_id
 * @property bool              $is_enabled
 * @property string|null       $credentials
 * @property string|null       $oauth_token
 * @property string|null       $oauth_refresh_token
 * @property Carbon|null       $oauth_expires_at
 * @property array<mixed>|null $scopes_granted
 * @property Carbon|null       $last_used_at
 * @property string|null       $last_error
 * @property array<mixed>|null $metadata
 * @property Carbon|null       $created_at
 * @property Carbon|null       $updated_at
 * @property Carbon|null       $deleted_at
 */
class UserIntegration extends Model
{
    use HasUlids;
    use SoftDeletes;

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
            'is_enabled'          => 'boolean',
            'credentials'         => 'encrypted',
            'oauth_token'         => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_expires_at'    => 'datetime',
            'scopes_granted'      => 'array',
            'last_used_at'        => 'datetime',
            'metadata'            => 'array',
        ];
    }

    /** @return BelongsTo<User, UserIntegration> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<IntegrationDefinition, UserIntegration> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(IntegrationDefinition::class, 'integration_id');
    }
}
