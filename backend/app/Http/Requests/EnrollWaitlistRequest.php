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
            'specialtyId' => ['required', 'uuid', 'exists:specialties,id'],
            'doctorId' => ['required', 'uuid', 'exists:doctors,id'],
            'preferred' => ['sometimes', 'nullable', 'string', 'max:160'],
        ];
    }
}
