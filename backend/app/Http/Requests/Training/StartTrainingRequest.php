<?php

declare(strict_types=1);

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class StartTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('training.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'dataset_id'        => ['required', 'string', 'exists:training_datasets,id'],
            'base_model_id'     => ['required', 'string', 'exists:ai_models,id'],
            'output_model_name' => ['required', 'string', 'max:255'],
            'config'            => ['nullable', 'array'],
        ];
    }
}
