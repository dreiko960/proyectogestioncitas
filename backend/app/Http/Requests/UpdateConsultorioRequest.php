<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConsultorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:80'],
            'piso' => ['sometimes', 'string', 'max:20'],
            'area' => ['sometimes', 'nullable', 'string', 'max:80'],
            'activo' => ['sometimes', 'boolean'],
            'specialtyIds' => ['sometimes', 'array'],
            'specialtyIds.*' => ['uuid', 'exists:specialties,id'],
        ];
    }
}
