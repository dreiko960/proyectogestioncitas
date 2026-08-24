<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\ApiController;
use App\Http\Requests\CompleteTriageRequest;
use App\Http\Requests\StartTriageRequest;
use App\Models\Appointment;
use App\Models\Triage;
use App\Services\AppointmentService;
use App\Support\QueueBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class TriageController extends ApiController
{
    public function __construct(private readonly AppointmentService $appointments) {}

    
    public function queue(): JsonResponse
    {
        $waiting = Appointment::with(['patient.user', 'doctor.user'])
            ->where('date', now()->toDateString())
            ->where('status', AppointmentStatus::EnEsperaTriaje)
            ->orderBy('turno')
            ->get();

        $inProgress = Appointment::with(['patient.user', 'doctor.user'])
            ->where('date', now()->toDateString())
            ->whereIn('status', [AppointmentStatus::EnTriaje, AppointmentStatus::TriajeCompletado])
            ->orderBy('turno')
            ->get();

        return $this->success([
            'waiting' => $waiting->map(fn ($a) => $this->payload($a))->all(),
            'in_progress' => $inProgress->map(fn ($a) => $this->payload($a))->all(),
        ]);
    }

    
    public function history(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $triages = Triage::with(['appointment.patient.user', 'appointment.doctor.user', 'nurse'])
            ->whereHas('appointment', fn ($q) => $q->where('date', $date))
            ->orderByDesc('at')
            ->get();

        return $this->success([
            'date' => $date,
            'items' => $triages->map(fn ($t) => [
                'id' => $t->id,
                'appointment' => [
                    'id' => $t->appointment->id,
                    'code' => $t->appointment->code,
                    'turno' => $t->appointment->turno,
                    'time' => $t->appointment->time,
                    'patient' => $t->appointment->patient?->user->name,
                ],
                'nurse' => $t->nurse?->name,
                'pa' => $t->pa,
                'temp' => $t->temp,
                'fc' => $t->fc,
                'peso' => $t->peso,
                'talla' => $t->talla,
                'motivo' => $t->motivo,
                'alergias' => $t->alergias,
                'observaciones' => $t->observaciones,
                'at' => $t->at?->toIso8601String(),
            ])->all(),
        ]);
    }

    
    public function start(StartTriageRequest $request, string $id): JsonResponse
    {
        $appointment = $this->find($id);

        if ($appointment->status !== AppointmentStatus::EnEsperaTriaje) {
            return $this->error('Solo se inicia el triaje desde en_espera_triaje', 422);
        }

        Triage::query()->create([
            'appointment_id' => $appointment->id,
            'nurse_id' => $request->user()->id,
            'motivo' => $request->validated('motivo'),
            'alergias' => $request->validated('alergias'),
            'observaciones' => $request->validated('observaciones'),
            'at' => now(),
        ]);

        $this->appointments->transition($appointment, AppointmentStatus::EnTriaje, $request->user());
        QueueBroadcaster::dispatch($appointment);

        return $this->success([
            'appointment' => $appointment->refresh()->id,
            'status' => 'en_triaje',
        ], 201);
    }

    
    public function complete(CompleteTriageRequest $request, string $id): JsonResponse
    {
        $appointment = $this->find($id);

        if (! in_array($appointment->status, [AppointmentStatus::EnTriaje, AppointmentStatus::TriajeCompletado], true)) {
            return $this->error('El triaje debe estar en progreso', 422);
        }

        $triage = Triage::query()->firstOrNew(['appointment_id' => $appointment->id]);
        $triage->nurse_id = $request->user()->id;
        $triage->fill($request->validated());
        $triage->at = now();
        $triage->save();

        if ($appointment->status !== AppointmentStatus::TriajeCompletado) {
            $this->appointments->transition($appointment, AppointmentStatus::TriajeCompletado, $request->user());
            QueueBroadcaster::dispatch($appointment);
        }

        return $this->success([
            'id' => $triage->id,
            'appointment_id' => $appointment->id,
            'status' => 'triaje_completado',
            'pa' => $triage->pa,
            'temp' => $triage->temp,
            'fc' => $triage->fc,
            'peso' => $triage->peso,
            'talla' => $triage->talla,
        ]);
    }

    private function find(string $id): Appointment
    {
        $appointment = Appointment::find($id);

        if (! $appointment) {
            abort(404, 'Cita no encontrada');
        }

        return $appointment;
    }

    private function payload(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'code' => $appointment->code,
            'turno' => $appointment->turno,
            'time' => $appointment->time,
            'status' => $appointment->status->value,
            'patient' => [
                'id' => $appointment->patient->id,
                'name' => $appointment->patient->user->name,
            ],
            'doctor' => [
                'id' => $appointment->doctor->id,
                'name' => $appointment->doctor->user->name,
            ],
        ];
    }
}
