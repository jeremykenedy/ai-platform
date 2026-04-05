<?php

declare(strict_types=1);

namespace App\Http\Requests\Model;

use App\Models\AiModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', AiModel::class);
    }

    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
