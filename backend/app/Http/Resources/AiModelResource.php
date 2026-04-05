<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_id' => $this->provider_id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'version' => $this->version,
            'context_window' => $this->context_window,
            'max_output_tokens' => $this->max_output_tokens,
            'capabilities' => $this->capabilities,
            'supports_vision' => $this->supports_vision,
            'supports_functions' => $this->supports_functions,
            'supports_streaming' => $this->supports_streaming,
            'parameter_count' => $this->parameter_count,
            'quantization' => $this->quantization,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'is_local' => $this->is_local,
            'update_available' => $this->update_available,
            'input_cost_per_1k' => $this->input_cost_per_1k,
            'output_cost_per_1k' => $this->output_cost_per_1k,
            'last_updated_at' => $this->last_updated_at,
            'provider' => $this->whenLoaded('provider', fn () => [
                'name' => $this->provider->name,
                'display_name' => $this->provider->display_name,
            ]),
        ];
    }
}
