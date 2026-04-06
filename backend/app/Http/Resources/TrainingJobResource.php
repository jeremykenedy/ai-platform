<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TrainingJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrainingJob */
class TrainingJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'output_model_name' => $this->output_model_name,
            'status'            => $this->status,
            'progress'          => $this->progress,
            'started_at'        => $this->started_at,
            'completed_at'      => $this->completed_at,
            'created_at'        => $this->created_at,
            'dataset'           => new TrainingDatasetResource($this->whenLoaded('dataset')),
            'base_model'        => $this->whenLoaded('baseModel', fn () => [
                'name'         => $this->baseModel?->name,
                'display_name' => $this->baseModel?->display_name,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id'   => $this->user?->id,
                'name' => $this->user?->name,
            ]),
            'log_output' => $this->when(
                $this->resource->relationLoaded('dataset') || $request->routeIs('*.show'),
                $this->log_output
            ),
        ];
    }
}
