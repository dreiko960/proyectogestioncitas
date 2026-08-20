<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChargePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointmentId' => ['required', 'uuid', 'exists:appointments,id'],
            'type' => ['required', Rule::in(['adelanto', 'total'])],
            'culqiToken' => ['required', 'string'],
        ];
    }
}
