<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return Gate::allows('update', $conversation);
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'string', 'exists:projects,id'],
            'persona_id' => ['nullable', 'string', 'exists:personas,id'],
            'model_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
