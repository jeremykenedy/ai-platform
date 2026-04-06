<?php

declare(strict_types=1);

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteToolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'integration_name' => ['required', 'string'],
            'tool_name'        => ['required', 'string'],
            'params'           => ['nullable', 'array'],
        ];
    }
}
