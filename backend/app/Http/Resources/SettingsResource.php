<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'theme' => $this->theme,
            'font_size' => $this->font_size,
            'send_on_enter' => $this->send_on_enter,
            'show_token_counts' => $this->show_token_counts,
            'memory_enabled' => $this->memory_enabled,
            'default_model_id' => $this->default_model_id,
            'default_persona_id' => $this->default_persona_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
