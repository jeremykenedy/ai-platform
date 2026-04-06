<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Persona */
class PersonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'system_prompt' => $this->system_prompt,
            'model_name' => $this->model_name,
            'temperature' => $this->temperature,
            'top_p' => $this->top_p,
            'top_k' => $this->top_k,
            'repeat_penalty' => $this->repeat_penalty,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
