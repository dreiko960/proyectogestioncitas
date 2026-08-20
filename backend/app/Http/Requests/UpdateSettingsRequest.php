<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'minCancelHours' => ['sometimes', 'numeric', 'min:0'],
            'minReserveHours' => ['sometimes', 'numeric', 'min:0'],
            'tokenExpiryMin' => ['sometimes', 'integer', 'min:1'],
            'waitlistWindowMin' => ['sometimes', 'integer', 'min:1'],
            'lateFeeDays' => ['sometimes', 'integer', 'min:0'],
            'nonWorkingDays' => ['sometimes', 'array'],
            'nonWorkingDays.*' => ['date', 'date_format:Y-m-d'],
        ];
    }
}
