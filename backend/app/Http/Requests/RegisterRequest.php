<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)->letters()->numbers()->mixedCase()],
            'dni' => ['required', 'digits:8', 'unique:patients,dni'],
            'phone' => ['nullable', 'string', 'digits_between:9,15', 'unique:patients,phone'],
            'dob' => ['required', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:255'],
            'consent_29733' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent_29733.accepted' => 'Debe aceptar el consentimiento de la Ley N.º 29733.',
            'password.mixedCase' => 'La contraseña debe contener mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
        ];
    }
}
