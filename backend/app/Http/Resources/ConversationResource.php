<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'title'                => $this->title,
            'model_name'           => $this->model_name,
            'context_window_used'  => $this->context_window_used,
            'enabled_integrations' => $this->enabled_integrations,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'user'                 => new UserResource($this->whenLoaded('user')),
            'project'              => new ProjectResource($this->whenLoaded('project')),
            'persona'              => new PersonaResource($this->whenLoaded('persona')),
            'messages'             => new MessageCollection($this->whenLoaded('messages')),
            'summaries_count'      => $this->whenCounted('summaries'),
        ];
    }
}
