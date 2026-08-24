<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AuditSev;
use App\Enums\PaidType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\SlotConflictException;
use App\Models\Appointment;
use App\Models\AppointmentStatusLog;
use App\Models\Diagnosis;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\User;
use App\Support\Codes;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class AppointmentService
{
    public const SLOT_MINUTES = 30;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array{doctorId: string, specialtyId: string, date: string, time: string, duration?: int, reason?: ?string, payOnline?: ?array{type?: string, culqiToken?: ?string}}  $data
     */
    public function reserve(Patient $patient, array $data, ?User $by = null, bool $enforceMinReserve = true): Appointment
    {
        $doctor = Doctor::with(['specialty', 'user'])->findOrFail($data['doctorId']);
        $specialty = Specialty::findOrFail($data['specialtyId']);
        $date = Carbon::parse($data['date']);
        $time = $data['time'];
        $duration = (int) ($data['duration'] ?? self::SLOT_MINUTES);

        $this->validateDoctorSlot($doctor, $specialty, $date, $time, $duration);

        if ($enforceMinReserve && (! $by || $by->role === UserRole::Paciente)) {
            $this->validateMinReserveHours($date, $time);
        }

        try {
            $appointment = DB::transaction(function () use ($patient, $doctor, $specialty, $date, $time, $duration, $data, $by) {
                $this->lockSlot($doctor, $date, $time);

                if ($this->hasConflict($doctor, $date, $time)) {
                    throw new SlotConflictException($this->alternatives($doctor, $date, $time));
                }

                $appointment = Appointment::create([
                    'code' => Codes::next('citas', 'C'),
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'specialty_id' => $specialty->id,
                    'date' => $date->toDateString(),
                    'time' => $time,
                    'duration_min' => $duration,
                    'status' => AppointmentStatus::Agendada,
                    'reason' => $data['reason'] ?? null,
                ]);

                $this->logTransition(null, AppointmentStatus::Agendada, $appointment, $by);

                $this->audit->record($by, 'Cita reservada', "{$appointment->code} · {$doctor->user->name} · {$date->toDateString()} {$time}", AuditSev::Info);

                return $appointment;
            });
        } catch (QueryException $e) {
            
            if ($e->getCode() === '23505') {
                throw new SlotConflictException($this->alternatives($doctor, $date, $time));
            }

            throw $e;
        }

        $payOnline = $data['payOnline'] ?? null;

        if (is_array($payOnline) && ($payOnline['culqiToken'] ?? null) && ($payOnline['type'] ?? null)) {
            $this->payments->chargeAppointment(
                $appointment,
                PaidType::from($payOnline['type']),
                $payOnline['culqiToken'],
                $by,
            );
            $this->transition($appointment, AppointmentStatus::Pagada, $by);
        }

        return $appointment->refresh();
    }

    
    public function freeSlots(Doctor $doctor, Carbon $from, Carbon $to): array
    {
        $slots = [];
        $cursor = $from->copy()->startOfDay();
        $nonWorking = Setting::get('nonWorkingDays', []);
        $blockedDates = $doctor->exceptions()->pluck('date')->map(fn ($d) => $d->format('Y-m-d'))->all();
        $booked = $this->bookedSlots($doctor, $from, $to);

        while ($cursor->lte($to->copy()->endOfDay())) {
            $dateStr = $cursor->toDateString();

            if (! in_array($dateStr, $nonWorking, true) && ! in_array($dateStr, $blockedDates, true)) {
                $schedules = $doctor->schedules()->where('day_of_week', $cursor->dayOfWeek)->orderBy('start_time')->get();

                foreach ($schedules as $schedule) {
                    foreach ($this->generateSlots($schedule) as $time) {
                        if (! in_array($dateStr.' '.$time, $booked, true)) {
                            $slots[] = [
                                'doctorId' => $doctor->id,
                                'specialtyId' => $doctor->specialty_id,
                                'date' => $dateStr,
                                'time' => $time,
                                'price' => (float) $doctor->specialty->price,
                            ];
                        }
                    }
                }
            }

            $cursor->addDay();
        }

        return $slots;
    }

    
    public function alternatives(Doctor $doctor, Carbon $date, string $time, int $count = 3): array
    {
        $from = $date->copy()->setTimeFromTimeString($time);
        $to = $from->copy()->addDays(7);

        return collect($this->freeSlots($doctor, $from, $to))
            ->filter(fn ($s) => $from->toDateTimeString() < $s['date'].' '.$s['time'])
            ->take($count)
            ->values()
            ->all();
    }

    
    public function saveDiagnosis(Appointment $appointment, string $dx, ?string $notes, ?User $by = null): Appointment
    {
        if ($appointment->status !== AppointmentStatus::EnAtencion) {
            throw new \InvalidArgumentException('El diagnóstico solo se registra en una cita en atención');
        }

        return DB::transaction(function () use ($appointment, $dx, $notes, $by) {
            $diagnosis = Diagnosis::query()->firstOrNew(['appointment_id' => $appointment->id]);
            $diagnosis->doctor_id = $by?->id;
            $diagnosis->dx = $dx;
            $diagnosis->notes = $notes;
            $diagnosis->at = now();
            $diagnosis->save();

            $this->transition($appointment, AppointmentStatus::Documentada, $by);
            $this->audit->record($by, 'Diagnóstico registrado', "{$appointment->code}", AuditSev::Info);

            return $appointment->refresh();
        });
    }

    
    public function checkIn(Appointment $appointment, ?User $by = null): Appointment
    {
        $this->transition($appointment, AppointmentStatus::CheckIn, $by);

        $this->audit->record($by, 'Check-in móvil', "Cita {$appointment->code}", AuditSev::Info);

        return $appointment->refresh();
    }

    
    public function sendToTriage(Appointment $appointment, ?User $by = null): Appointment
    {
        if (! in_array($appointment->status, [AppointmentStatus::Pagada, AppointmentStatus::CheckIn], true)) {
            throw new \InvalidArgumentException('Solo citas pagadas o con check-in móvil entran a la cola');
        }

        return DB::transaction(function () use ($appointment, $by) {
            $turno = $this->nextTurno(Carbon::parse($appointment->date));

            $appointment->turno = $turno;
            $appointment->check_in_time = now()->format('H:i');
            $appointment->save();

            $this->transition($appointment, AppointmentStatus::EnEsperaTriaje, $by);
            $this->audit->record($by, 'Check-in presencial', "Cita {$appointment->code} · turno {$turno}", AuditSev::Info);

            return $appointment->refresh();
        });
    }

    
    public function cancel(Appointment $appointment, string $reason, ?User $by = null): array
    {
        if (! in_array($appointment->status, [
            AppointmentStatus::Agendada,
            AppointmentStatus::Pagada,
            AppointmentStatus::Reprogramada,
        ], true)) {
            throw new \InvalidArgumentException('La cita no se puede cancelar en su estado actual');
        }

        $late = $this->isLateCancellation($appointment);

        $appointment->update([
            'status' => AppointmentStatus::Cancelada,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        $this->logTransition($appointment->getOriginal('status'), AppointmentStatus::Cancelada, $appointment, $by);
        $this->audit->record(
            $by,
            'Cita cancelada',
            "{$appointment->code} · {$reason} · ".($late ? 'cancelación tardía' : 'a tiempo'),
            $late ? AuditSev::Warning : AuditSev::Info,
        );

        if ($late && $appointment->payments()->where('gateway', true)->where('status', PaymentStatus::Pagado)->exists()) {
            $this->audit->warning($by, 'Cita cancelada', "{$appointment->code} · reembolso Culqi pendiente de política (§6.3)");
        }

        return ['warning' => $late];
    }

    
    public function reschedule(Appointment $appointment, string $newDate, string $newTime, ?User $by = null): Appointment
    {
        $date = Carbon::parse($newDate);
        $doctor = $appointment->doctor;

        $this->validateDoctorSlot($doctor, $appointment->specialty, $date, $newTime, $appointment->duration_min);

        return DB::transaction(function () use ($appointment, $doctor, $date, $newTime, $by) {
            $this->lockSlot($doctor, $date, $newTime);

            if ($this->hasConflict($doctor, $date, $newTime, $appointment->id)) {
                throw new SlotConflictException($this->alternatives($doctor, $date, $newTime));
            }

            $oldStatus = $appointment->status;

            $appointment->update([
                'date' => $date->toDateString(),
                'time' => $newTime,
                'status' => AppointmentStatus::Reprogramada,
                'rescheduled_to' => $appointment->date,
                'turno' => null,
            ]);

            $this->logTransition($oldStatus, AppointmentStatus::Reprogramada, $appointment, $by);
            $this->audit->record($by, 'Cita reprogramada', "{$appointment->code} · nueva fecha {$date->toDateString()} {$newTime}", AuditSev::Info);

            return $appointment->refresh();
        });
    }

    public function transition(Appointment $appointment, AppointmentStatus $to, ?User $by = null): void
    {
        $from = $appointment->status;
        $allowed = $this->allowedTransitions($from);

        if (! $allowed->contains($to)) {
            throw new \InvalidArgumentException("Transición inválida: {$from->value} → {$to->value}");
        }

        $appointment->status = $to;
        $appointment->save();

        $this->logTransition($from, $to, $appointment, $by);
        $this->audit->record($by, 'Cita '.$to->label(), "{$appointment->code} · {$from->value} → {$to->value}", AuditSev::Info);
    }

    public function nextTurno(Carbon $date): string
    {
        $max = DB::table('citas')
            ->whereDate('date', $date->toDateString())
            ->whereNotNull('turno')
            ->max('turno');

        $next = $max ? ((int) substr($max, 2)) + 1 : 1;

        return 'A-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function isLateCancellation(Appointment $appointment): bool
    {
        $start = Carbon::parse($appointment->date->format('Y-m-d').' '.$appointment->time);
        $minHours = (float) Setting::get('minCancelHours', 12);

        return now()->diffInHours($start, false) < $minHours;
    }

    public function queueOfDay(string $date): array
    {
        return Appointment::with(['patient.user', 'doctor.user', 'specialty'])
            ->where('date', $date)
            ->whereIn('status', [
                AppointmentStatus::EnEsperaTriaje,
                AppointmentStatus::EnTriaje,
                AppointmentStatus::TriajeCompletado,
                AppointmentStatus::EnAtencion,
            ])
            ->orderBy('turno')
            ->get()
            ->all();
    }

    
    private function lockSlot(Doctor $doctor, Carbon $date, string $time): void
    {
        DoctorSchedule::query()
            ->where('doctor_id', $doctor->id)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('start_time', $time)
            ->lockForUpdate()
            ->first();
    }

    private function hasConflict(Doctor $doctor, Carbon $date, string $time, ?string $ignoreId = null): bool
    {
        return Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->where('date', $date->toDateString())
            ->where('time', $time)
            ->whereNotIn('status', [AppointmentStatus::Cancelada, AppointmentStatus::Reprogramada])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    private function bookedSlots(Doctor $doctor, Carbon $from, Carbon $to): array
    {
        return Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [AppointmentStatus::Cancelada, AppointmentStatus::Reprogramada])
            ->get()
            ->map(fn ($a) => $a->date->format('Y-m-d').' '.$a->time)
            ->all();
    }

    private function generateSlots(DoctorSchedule $schedule): array
    {
        $start = Carbon::parse($schedule->start_time);
        $end = Carbon::parse($schedule->end_time);
        $slots = [];

        while ($start->copy()->addMinutes(self::SLOT_MINUTES)->lte($end)) {
            $slots[] = $start->format('H:i');
            $start->addMinutes(self::SLOT_MINUTES);
        }

        return $slots;
    }

    private function validateDoctorSlot(Doctor $doctor, Specialty $specialty, Carbon $date, string $time, int $duration): void
    {
        if ($doctor->specialty_id !== $specialty->id) {
            throw new \InvalidArgumentException('La especialidad no corresponde al médico');
        }

        if (in_array($date->toDateString(), Setting::get('nonWorkingDays', []), true)) {
            throw new \InvalidArgumentException('El día seleccionado no es laborable');
        }

        if ($doctor->exceptions()->where('date', $date->toDateString())->exists()) {
            throw new \InvalidArgumentException('El médico no atiende en esa fecha');
        }

        $schedule = $doctor->schedules()
            ->where('day_of_week', $date->dayOfWeek)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->first();

        if (! $schedule) {
            throw new \InvalidArgumentException('El médico no atiende en ese horario');
        }

        $start = Carbon::parse($time);
        $end = Carbon::parse($schedule->end_time);

        if ($start->copy()->addMinutes($duration)->gt($end)) {
            throw new \InvalidArgumentException('La duración excede la franja disponible');
        }
    }

    private function validateMinReserveHours(Carbon $date, string $time): void
    {
        $minHours = (float) Setting::get('minReserveHours', 2);
        $start = Carbon::parse($date->format('Y-m-d').' '.$time);

        if (now()->diffInHours($start, false) < $minHours) {
            throw new \InvalidArgumentException("La reserva debe hacerse con al menos {$minHours} h de anticipación");
        }
    }

    
    private function allowedTransitions(AppointmentStatus $from): Collection
    {
        return match ($from) {
            AppointmentStatus::Agendada => collect([
                AppointmentStatus::Pagada,
                AppointmentStatus::CheckIn,
                AppointmentStatus::Cancelada,
                AppointmentStatus::Reprogramada,
            ]),
            AppointmentStatus::Confirmada => collect([
                AppointmentStatus::Pagada,
                AppointmentStatus::Cancelada,
                AppointmentStatus::Reprogramada,
            ]),
            AppointmentStatus::Pagada => collect([
                AppointmentStatus::CheckIn,
                AppointmentStatus::EnEsperaTriaje,
                AppointmentStatus::Cancelada,
                AppointmentStatus::Reprogramada,
            ]),
            AppointmentStatus::CheckIn => collect([AppointmentStatus::EnEsperaTriaje]),
            AppointmentStatus::EnEsperaTriaje => collect([AppointmentStatus::EnTriaje]),
            AppointmentStatus::EnTriaje => collect([AppointmentStatus::TriajeCompletado]),
            AppointmentStatus::TriajeCompletado => collect([AppointmentStatus::EnAtencion]),
            AppointmentStatus::EnAtencion => collect([AppointmentStatus::Atendida, AppointmentStatus::Documentada]),
            default => collect(),
        };
    }

    private function logTransition(?AppointmentStatus $from, AppointmentStatus $to, Appointment $appointment, ?User $by = null): void
    {
        AppointmentStatusLog::create([
            'appointment_id' => $appointment->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'by_user_id' => $by?->id,
            'at' => now(),
        ]);
    }
}
