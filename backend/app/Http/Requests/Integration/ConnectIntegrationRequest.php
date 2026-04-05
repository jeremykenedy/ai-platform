<?php

declare(strict_types=1);

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class ConnectIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'integration_name' => ['required', 'string', 'exists:integration_definitions,name'],
            'credentials' => ['nullable', 'array'],
            'api_key' => ['nullable', 'string'],
        ];
    }
}
