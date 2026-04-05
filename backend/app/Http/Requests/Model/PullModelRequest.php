<?php

declare(strict_types=1);

namespace App\Http\Requests\Model;

use App\Models\AiModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PullModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('pull', AiModel::class);
    }

    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'max:255'],
        ];
    }
}
