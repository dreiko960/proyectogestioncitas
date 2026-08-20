<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointmentId' => ['required', 'uuid', 'exists:appointments,id'],
            'method' => ['required', Rule::in(['efectivo', 'yape', 'plin', 'transferencia'])],
            'type' => ['sometimes', Rule::in(['adelanto', 'total'])],
        ];
    }
}
