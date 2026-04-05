<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['nullable', 'string', 'in:system,light,dark'],
            'font_size' => ['nullable', 'integer', 'min:10', 'max:24'],
            'send_on_enter' => ['nullable', 'boolean'],
            'show_token_counts' => ['nullable', 'boolean'],
            'memory_enabled' => ['nullable', 'boolean'],
            'default_model_id' => ['nullable', 'string', 'exists:ai_models,id'],
            'default_persona_id' => ['nullable', 'string', 'exists:personas,id'],
        ];
    }
}
