<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'role' => ['required', Rule::in(['paciente', 'medico', 'enfermera', 'recepcionista', 'administrador'])],
            'doctorData' => ['required_if:role,medico', 'array'],
            'doctorData.initials' => ['required_if:role,medico', 'string', 'max:5'],
            'doctorData.specialtyId' => ['required_if:role,medico', 'uuid', 'exists:specialties,id'],
            'doctorData.consultorioId' => ['sometimes', 'nullable', 'uuid', 'exists:consultorios,id'],
            'doctorData.phone' => ['sometimes', 'nullable', 'string', 'max:15'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'El correo ya está registrado',
            'role.in' => 'Rol inválido',
        ];
    }
}
