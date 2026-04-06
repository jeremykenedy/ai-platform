<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string            $id
 * @property string            $user_id
 * @property string            $dataset_id
 * @property string            $base_model_id
 * @property string            $output_model_name
 * @property array<mixed>|null $config
 * @property string            $status
 * @property int               $progress
 * @property string|null       $log_output
 * @property Carbon|null       $started_at
 * @property Carbon|null       $completed_at
 * @property Carbon|null       $created_at
 * @property Carbon|null       $updated_at
 * @property Carbon|null       $deleted_at
 * @property-read TrainingDataset|null $dataset
 * @property-read AiModel|null $baseModel
 * @property-read User|null $user
 */
class TrainingJob extends Model
{
    use HasUlids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'dataset_id',
        'base_model_id',
        'output_model_name',
        'config',
        'status',
        'progress',
        'log_output',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config'       => 'array',
            'progress'     => 'integer',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(TrainingDataset::class, 'dataset_id');
    }

    public function baseModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'base_model_id');
    }

    /**
     * @param Builder<TrainingJob> $query
     *
     * @return Builder<TrainingJob>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }

    /**
     * @param Builder<TrainingJob> $query
     *
     * @return Builder<TrainingJob>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param Builder<TrainingJob> $query
     *
     * @return Builder<TrainingJob>
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
