<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:160'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(6)->letters()->numbers()->mixedCase()],
        ];
    }

    public function messages(): array
    {
        return [
            'password.mixedCase' => 'La contraseña debe contener mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
        ];
    }
}
