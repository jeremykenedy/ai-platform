<?php

declare(strict_types=1);

namespace App\Http\Requests\Conversation;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Conversation::class);
    }

    public function rules(): array
    {
        return [
            'title'      => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'string', 'exists:projects,id'],
            'persona_id' => ['nullable', 'string', 'exists:personas,id'],
            'model_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
