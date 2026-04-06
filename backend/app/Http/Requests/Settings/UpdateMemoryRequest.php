<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'    => ['required', 'string'],
            'category'   => ['nullable', 'string', 'in:preference,fact,instruction,context,personality'],
            'importance' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
