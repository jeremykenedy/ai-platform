<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'category' => $this->category,
            'importance' => $this->importance,
            'last_accessed_at' => $this->last_accessed_at,
            'access_count' => $this->access_count,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'source_conversation' => $this->whenLoaded('sourceConversation', fn () => [
                'id' => $this->sourceConversation->id,
                'title' => $this->sourceConversation->title,
            ]),
        ];
    }
}
