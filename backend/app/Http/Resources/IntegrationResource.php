<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'category' => $this->category,
            'auth_type' => $this->auth_type,
            'icon_url' => $this->icon_url,
            'is_active' => $this->is_active,
            'is_connected' => $this->when(
                $this->resource->relationLoaded('userIntegration'),
                fn () => $this->userIntegration !== null
            ),
            'is_enabled' => $this->when(
                $this->resource->relationLoaded('userIntegration') && $this->userIntegration !== null,
                fn () => $this->userIntegration->is_enabled
            ),
            'last_used_at' => $this->when(
                $this->resource->relationLoaded('userIntegration') && $this->userIntegration !== null,
                fn () => $this->userIntegration->last_used_at
            ),
            'last_error' => $this->when(
                $this->resource->relationLoaded('userIntegration') && $this->userIntegration !== null,
                fn () => $this->userIntegration->last_error
            ),
        ];
    }
}
