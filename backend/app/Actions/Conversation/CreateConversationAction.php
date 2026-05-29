<?php

declare(strict_types=1);

namespace App\Actions\Conversation;

use App\Models\AiModel;
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
            'model_name' => $this->resolveModelName($data['model_name'] ?? null),
        ]);

        return $conversation;
    }

    /**
     * Resolve AiModel ULID id → canonical name. The SPA sends the model
     * record's id; we want to persist the name so inference works and the
     * id doesn't leak into the message metadata.
     */
    private function resolveModelName(?string $idOrName): ?string
    {
        if ($idOrName === null || $idOrName === '') {
            return null;
        }

        if (preg_match('/^[0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{26}$/', $idOrName) === 1) {
            $model = AiModel::find($idOrName);
            if ($model !== null) {
                return $model->name;
            }
        }

        return $idOrName;
    }
}
