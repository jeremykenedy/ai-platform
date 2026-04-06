<?php

declare(strict_types=1);

namespace App\Http\Requests\Persona;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $persona = $this->route('persona');

        return Gate::allows('update', $persona);
    }

    public function rules(): array
    {
        return [
            'name'           => ['nullable', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'system_prompt'  => ['nullable', 'string'],
            'model_name'     => ['nullable', 'string'],
            'temperature'    => ['nullable', 'numeric', 'between:0,2'],
            'top_p'          => ['nullable', 'numeric', 'between:0,1'],
            'top_k'          => ['nullable', 'integer', 'min:1', 'max:200'],
            'repeat_penalty' => ['nullable', 'numeric', 'between:0.5,2'],
        ];
    }
}
