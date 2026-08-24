<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnrollWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'specialtyId' => ['required', 'uuid', 'exists:especialidades,id'],
            'doctorId' => ['required', 'uuid', 'exists:doctores,id'],
            'preferred' => ['sometimes', 'nullable', 'string', 'max:160'],
        ];
    }
}
