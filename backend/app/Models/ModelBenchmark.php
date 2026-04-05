<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelBenchmark extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'model_id',
        'category',
        'prompt_hash',
        'ttft_ms',
        'tokens_per_sec',
        'total_tokens',
        'quality_score',
        'ran_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ttft_ms' => 'integer',
            'tokens_per_sec' => 'float',
            'total_tokens' => 'integer',
            'quality_score' => 'float',
            'ran_at' => 'datetime',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }
}
