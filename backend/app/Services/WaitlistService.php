<?php

namespace App\Services;

use App\Enums\AuditSev;
use App\Enums\PaidType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\WaitlistStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Codes;
use Illuminate\Support\Facades\DB;

/**
 * Lista de espera (BACKEND.md §5.9, §6.4).
 * Posición = orden cronológico dentro de (specialty, doctor).
 */
class WaitlistService
{
    public function __construct(
        private readonly AppointmentService $appointments,
        private readonly AuditService $audit,
    ) {}

    public function enroll(Patient $patient, string $specialtyId, string $doctorId, ?string $preferred = null): WaitlistEntry
    {
        $position = DB::table('waitlist_entries')
            ->where('specialty_id', $specialtyId)
            ->where('doctor_id', $doctorId)
            ->whereIn('status', [WaitlistStatus::EnEspera->value, WaitlistStatus::Oferta->value])
            ->max('position') ?? 0;

        return WaitlistEntry::query()->create([
            'code' => Codes::next('waitlist_entries', 'WL'),
            'patient_id' => $patient->id,
            'specialty_id' => $specialtyId,
            'doctor_id' => $doctorId,
            'preferred' => $preferred,
            'position' => $position + 1,
            'status' => WaitlistStatus::EnEspera,
            'confirm_window_min' => (int) Setting::get('waitlistWindowMin', 15),
            'enrolled_at' => now(),
        ]);
    }

    /** Worker: asigna cupo al primero en espera (oferta con ventana). */
    public function offer(WaitlistEntry $entry, string $date, string $time): WaitlistEntry
    {
        $this->assertStatus($entry, [WaitlistStatus::EnEspera]);

        $window = (int) Setting::get('waitlistWindowMin', 15);

        $entry->update([
            'status' => WaitlistStatus::Oferta,
            'offer_date' => $date,
            'offer_time' => $time,
            'offer_expires_at' => now()->addMinutes($window),
            'confirm_window_min' => $window,
        ]);

        $this->audit->record(null, 'Oferta de cupo', "{$entry->code} · {$date} {$time}", AuditSev::Info);

        return $entry->refresh();
    }

    /** Paciente confirma → crea la cita automáticamente + pago pendiente de verificación. */
    public function confirm(WaitlistEntry $entry, ?User $by = null): Appointment
    {
        $this->assertStatus($entry, [WaitlistStatus::Oferta]);

        if ($entry->offer_expires_at && $entry->offer_expires_at->lt(now())) {
            throw new \RuntimeException('La oferta ha expirado');
        }

        $appointment = DB::transaction(function () use ($entry) {
            $appointment = $this->appointments->reserve(
                $entry->patient,
                [
                    'doctorId' => $entry->doctor_id,
                    'specialtyId' => $entry->specialty_id,
                    'date' => $entry->offer_date->toDateString(),
                    'time' => $entry->offer_time,
                    'duration' => 30,
                    'reason' => 'Confirmación de lista de espera',
                ],
                null,
                enforceMinReserve: false,
            );

            $appointment->payments()->create([
                'code' => Codes::next('payments', 'P'),
                'patient_id' => $entry->patient_id,
                'amount' => (float) $entry->specialty->price,
                'method' => PaymentMethod::Yape,
                'status' => PaymentStatus::PendienteVerificacion,
                'paid_type' => PaidType::Total,
            ]);

            $entry->update([
                'status' => WaitlistStatus::Confirmada,
                'created_appointment_id' => $appointment->id,
            ]);

            $this->renumber($entry->specialty_id, $entry->doctor_id);

            return $appointment;
        });

        $this->audit->record($by, 'Cupo confirmado', "{$entry->code} · cita {$appointment->code}", AuditSev::Info);

        return $appointment;
    }

    /** Rechaza la oferta → vuelve a en_espera (o se retira si ya no le interesa). */
    public function reject(WaitlistEntry $entry, ?User $by = null, bool $withdraw = false): WaitlistEntry
    {
        $this->assertStatus($entry, [WaitlistStatus::Oferta]);

        $entry->update([
            'status' => $withdraw ? WaitlistStatus::Retirada : WaitlistStatus::EnEspera,
            'offer_date' => null,
            'offer_time' => null,
            'offer_expires_at' => null,
        ]);

        $this->renumber($entry->specialty_id, $entry->doctor_id);

        $this->audit->record($by, 'Oferta rechazada', "{$entry->code}", AuditSev::Info);

        return $entry->refresh();
    }

    /** Worker: oferta expirada → el cupo pasa al siguiente. */
    public function expire(WaitlistEntry $entry): WaitlistEntry
    {
        $this->assertStatus($entry, [WaitlistStatus::Oferta]);

        $entry->update(['status' => WaitlistStatus::Expirada]);

        $this->renumber($entry->specialty_id, $entry->doctor_id);

        $this->audit->record(null, 'Oferta expirada', "{$entry->code}", AuditSev::Warning);

        return $entry->refresh();
    }

    public function assertStatus(WaitlistEntry $entry, array $allowed): void
    {
        if (! in_array($entry->status, $allowed, true)) {
            throw new \InvalidArgumentException('La inscripción no está en el estado requerido');
        }
    }

    /** Reordena posiciones de en_espera por fecha de inscripción (§6.4). */
    private function renumber(string $specialtyId, string $doctorId): void
    {
        $entries = WaitlistEntry::query()
            ->where('specialty_id', $specialtyId)
            ->where('doctor_id', $doctorId)
            ->where('status', WaitlistStatus::EnEspera)
            ->orderBy('enrolled_at')
            ->orderBy('id')
            ->get();

        foreach ($entries->values() as $i => $entry) {
            if ($entry->position !== $i + 1) {
                $entry->update(['position' => $i + 1]);
            }
        }
    }
}
