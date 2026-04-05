<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiProvider extends Model
{
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
            'is_active' => 'boolean',
            'is_configured' => 'boolean',
            'last_health_check_at' => 'datetime',
            'capabilities' => 'array',
            'config' => 'array',
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    /**
     * @param  Builder<AiProvider>  $query
     * @return Builder<AiProvider>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<AiProvider>  $query
     * @return Builder<AiProvider>
     */
    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('type', 'local');
    }

    /**
     * @param  Builder<AiProvider>  $query
     * @return Builder<AiProvider>
     */
    public function scopeRemote(Builder $query): Builder
    {
        return $query->where('type', 'remote');
    }

    /**
     * @param  Builder<AiProvider>  $query
     * @return Builder<AiProvider>
     */
    public function scopeConfigured(Builder $query): Builder
    {
        return $query->where('is_configured', true);
    }
}
