<?php

namespace App\Models;

use App\Enums\WaitlistStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'code',
        'patient_id',
        'specialty_id',
        'doctor_id',
        'preferred',
        'position',
        'status',
        'offer_date',
        'offer_time',
        'offer_expires_at',
        'confirm_window_min',
        'created_appointment_id',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'status' => WaitlistStatus::class,
            'offer_date' => 'date',
            'offer_expires_at' => 'datetime',
            'confirm_window_min' => 'integer',
            'enrolled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function createdAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'created_appointment_id');
    }
}
