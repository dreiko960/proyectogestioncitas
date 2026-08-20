<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartTriageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:1000'],
            'alergias' => ['sometimes', 'nullable', 'string', 'max:500'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
