<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $model_id
 * @property string      $category
 * @property string      $prompt_hash
 * @property int         $ttft_ms
 * @property float       $tokens_per_sec
 * @property int         $total_tokens
 * @property float|null  $quality_score
 * @property Carbon      $ran_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
            'ttft_ms'        => 'integer',
            'tokens_per_sec' => 'float',
            'total_tokens'   => 'integer',
            'quality_score'  => 'float',
            'ran_at'         => 'datetime',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }
}
