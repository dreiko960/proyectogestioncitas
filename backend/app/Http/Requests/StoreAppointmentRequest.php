<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doctorId' => ['required', 'uuid', 'exists:doctors,id'],
            'specialtyId' => ['required', 'uuid', 'exists:specialties,id'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'duration' => ['sometimes', 'integer', 'in:15,30,45,60'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'patientId' => ['sometimes', 'required', 'uuid', 'exists:patients,id'],
            'payOnline' => ['sometimes', 'array'],
            'payOnline.type' => ['required_with:payOnline', Rule::in(['adelanto', 'total'])],
            'payOnline.culqiToken' => ['required_with:payOnline', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'doctorId.exists' => 'El médico no existe',
            'specialtyId.exists' => 'La especialidad no existe',
            'date.date_format' => 'Formato de fecha inválido (Y-m-d)',
            'time.date_format' => 'Formato de hora inválido (H:i)',
        ];
    }
}
