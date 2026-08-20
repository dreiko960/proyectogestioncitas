<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:specialties,code'],
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['sometimes', 'string', 'max:30'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'desc' => ['sometimes', 'nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
