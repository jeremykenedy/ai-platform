<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiModel extends Model
{
    use HasUlids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'name',
        'ollama_model_id',
        'display_name',
        'description',
        'version',
        'context_window',
        'max_output_tokens',
        'capabilities',
        'supports_vision',
        'supports_functions',
        'supports_streaming',
        'input_cost_per_1k',
        'output_cost_per_1k',
        'parameter_count',
        'quantization',
        'ollama_digest',
        'is_active',
        'is_default',
        'is_local',
        'update_available',
        'last_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context_window' => 'integer',
            'max_output_tokens' => 'integer',
            'capabilities' => 'array',
            'supports_vision' => 'boolean',
            'supports_functions' => 'boolean',
            'supports_streaming' => 'boolean',
            'input_cost_per_1k' => 'float',
            'output_cost_per_1k' => 'float',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_local' => 'boolean',
            'update_available' => 'boolean',
            'last_updated_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function benchmarks(): HasMany
    {
        return $this->hasMany(ModelBenchmark::class, 'model_id');
    }

    /**
     * @param  Builder<AiModel>  $query
     * @return Builder<AiModel>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<AiModel>  $query
     * @return Builder<AiModel>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * @param  Builder<AiModel>  $query
     * @return Builder<AiModel>
     */
    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('is_local', true);
    }

    /**
     * @param  Builder<AiModel>  $query
     * @return Builder<AiModel>
     */
    public function scopeWithVision(Builder $query): Builder
    {
        return $query->where('supports_vision', true);
    }
}
