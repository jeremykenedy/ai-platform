<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return Gate::allows('update', $project);
    }

    public function rules(): array
    {
        return [
            'name'        => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'persona_id'  => ['nullable', 'string', 'exists:personas,id'],
        ];
    }
}
