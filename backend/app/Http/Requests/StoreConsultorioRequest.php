<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsultorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'piso' => ['required', 'string', 'max:20'],
            'area' => ['sometimes', 'nullable', 'string', 'max:80'],
            'activo' => ['sometimes', 'boolean'],
            'specialtyIds' => ['sometimes', 'array'],
            'specialtyIds.*' => ['uuid', 'exists:especialidades,id'],
        ];
    }
}
