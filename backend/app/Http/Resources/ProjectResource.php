<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'persona_id' => $this->persona_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'persona' => new PersonaResource($this->whenLoaded('persona')),
            'conversations_count' => $this->whenCounted('conversations'),
        ];
    }
}
