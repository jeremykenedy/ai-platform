<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string            $id
 * @property string            $name
 * @property string            $display_name
 * @property string            $type
 * @property string|null       $base_url
 * @property bool              $is_active
 * @property bool              $is_configured
 * @property string            $health_status
 * @property Carbon|null       $last_health_check_at
 * @property array<mixed>|null $capabilities
 * @property array<mixed>|null $config
 * @property Carbon|null       $created_at
 * @property Carbon|null       $updated_at
 */
class AiProvider extends Model
{
    /** @use HasFactory<AiProviderFactory> */
    use HasFactory;
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'type',
        'base_url',
        'is_active',
        'is_configured',
        'health_status',
        'last_health_check_at',
        'capabilities',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active'            => 'boolean',
            'is_configured'        => 'boolean',
            'last_health_check_at' => 'datetime',
            'capabilities'         => 'array',
            'config'               => 'array',
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    /**
     * @param Builder<AiProvider> $query
     *
     * @return Builder<AiProvider>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param Builder<AiProvider> $query
     *
     * @return Builder<AiProvider>
     */
    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('type', 'local');
    }

    /**
     * @param Builder<AiProvider> $query
     *
     * @return Builder<AiProvider>
     */
    public function scopeRemote(Builder $query): Builder
    {
        return $query->where('type', 'remote');
    }

    /**
     * @param Builder<AiProvider> $query
     *
     * @return Builder<AiProvider>
     */
    public function scopeConfigured(Builder $query): Builder
    {
        return $query->where('is_configured', true);
    }
}
