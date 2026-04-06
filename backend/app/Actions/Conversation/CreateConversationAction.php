<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Models\Conversation;
use App\Models\User;

class CreateConversationAction
{
    /**
     * Create a new conversation for the given user.
     *
     * @param array{title?: string|null, project_id?: string|null, persona_id?: string|null, model_name?: string|null} $data
     */
    public function handle(User $user, array $data): Conversation
    {
        /** @var Conversation $conversation */
        $conversation = Conversation::create([
            'user_id'    => $user->id,
            'title'      => $data['title'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'persona_id' => $data['persona_id'] ?? null,
            'model_name' => $data['model_name'] ?? null,
        ]);

        return $conversation;
    }
}
