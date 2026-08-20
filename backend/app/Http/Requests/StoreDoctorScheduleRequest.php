<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedules.*.day_of_week.between' => 'Día inválido (0=Domingo … 6=Sábado)',
            'schedules.*.end_time.after' => 'El fin debe ser después del inicio',
        ];
    }
}
