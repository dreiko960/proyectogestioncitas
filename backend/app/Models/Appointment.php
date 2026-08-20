<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\PaidType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'code',
        'patient_id',
        'doctor_id',
        'specialty_id',
        'date',
        'time',
        'duration_min',
        'status',
        'reason',
        'check_in_time',
        'turno',
        'paid_type',
        'cancelled_at',
        'cancel_reason',
        'rescheduled_to',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_min' => 'integer',
            'status' => AppointmentStatus::class,
            'paid_type' => PaidType::class,
            'cancelled_at' => 'datetime',
            'rescheduled_to' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function triage(): HasOne
    {
        return $this->hasOne(Triage::class);
    }

    public function diagnosis(): HasOne
    {
        return $this->hasOne(Diagnosis::class);
    }

    public function waitlistEntry(): HasOne
    {
        return $this->hasOne(WaitlistEntry::class, 'created_appointment_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [AppointmentStatus::Cancelada, AppointmentStatus::Reprogramada]);
    }

    public function scopeQueuedToday(Builder $query): Builder
    {
        return $query->where('date', now()->toDateString())
            ->whereIn('status', [
                AppointmentStatus::EnEsperaTriaje,
                AppointmentStatus::EnTriaje,
                AppointmentStatus::TriajeCompletado,
                AppointmentStatus::EnAtencion,
            ])
            ->orderBy('turno');
    }
}
