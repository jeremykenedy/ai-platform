<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role,
            'content' => $this->content,
            'tokens_used' => $this->tokens_used,
            'finish_reason' => $this->finish_reason,
            'model_version' => $this->model_version,
            'sequence' => $this->sequence,
            'created_at' => $this->created_at,
            'attachments' => $this->whenLoaded('attachments'),
            'edits_count' => $this->whenCounted('edits'),
        ];
    }
}
