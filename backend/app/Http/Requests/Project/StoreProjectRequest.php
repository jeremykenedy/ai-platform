<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'persona_id'  => ['nullable', 'string', 'exists:personas,id'],
        ];
    }
}
