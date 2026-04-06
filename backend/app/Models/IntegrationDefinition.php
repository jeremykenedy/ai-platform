<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property string $category
 * @property string $auth_type
 * @property array<mixed>|null $oauth_scopes
 * @property string|null $icon_url
 * @property bool $is_active
 * @property string|null $requires_permission
 * @property string|null $documentation_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserIntegration|null $userIntegration
 */
class IntegrationDefinition extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category',
        'auth_type',
        'oauth_scopes',
        'icon_url',
        'is_active',
        'requires_permission',
        'documentation_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'oauth_scopes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function userIntegrations(): HasMany
    {
        return $this->hasMany(UserIntegration::class, 'integration_id');
    }

    /**
     * @param  Builder<IntegrationDefinition>  $query
     * @return Builder<IntegrationDefinition>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<IntegrationDefinition>  $query
     * @return Builder<IntegrationDefinition>
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
