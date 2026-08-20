<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteTriageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pa' => ['sometimes', 'nullable', 'string', 'max:12'],
            'temp' => ['sometimes', 'nullable', 'numeric', 'between:30,45'],
            'fc' => ['sometimes', 'nullable', 'integer', 'between:20,250'],
            'peso' => ['sometimes', 'nullable', 'numeric', 'between:0.5,300'],
            'talla' => ['sometimes', 'nullable', 'numeric', 'between:0.3,2.5'],
            'motivo' => ['sometimes', 'string', 'max:1000'],
            'alergias' => ['sometimes', 'nullable', 'string', 'max:500'],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
