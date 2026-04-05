<?php

declare(strict_types=1);

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        if (! $conversation) {
            return false;
        }

        return $this->user()?->id === $conversation->user_id;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'model' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:52428800'],
            'stream' => ['nullable', 'boolean'],
        ];
    }
}
