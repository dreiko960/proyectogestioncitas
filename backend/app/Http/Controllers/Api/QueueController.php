<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\ApiController;
use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Support\QueueBroadcaster;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/** Cola del día (recepción/enfermería) + pantalla TV (BACKEND.md §5.8). */
class QueueController extends ApiController
{
    public function __construct(private readonly AppointmentService $appointments) {}

    /** POST /api/tv/token · emite token de solo lectura para la pantalla (clave de consultorio). */
    public function tvToken(Request $request): JsonResponse
    {
        $key = $request->input('key');
        $expected = config('services.tv_key');

        if (! $key || ! $expected || ! hash_equals((string) $expected, (string) $key)) {
            return $this->error('Clave inválida', 401);
        }

        $token = Crypt::encryptString(json_encode([
            'aud' => 'tv',
            'exp' => now()->addDay()->timestamp,
        ]));

        return $this->success([
            'token' => $token,
            'expires_at' => now()->addDay()->toIso8601String(),
        ]);
    }

    /** GET /api/queue/day?date= · pipeline ordenada por turno + stats en vivo. */
    public function day(Request $request): JsonResponse
    {
        if (! $this->authorizeTv($request)) {
            return $this->error('No autenticado', 401);
        }

        $date = $request->query('date', now()->toDateString());
        $items = $this->appointments->queueOfDay($date);

        $statuses = collect($items)->groupBy(fn ($a) => $a->status->value)->map->count();

        return $this->success([
            'date' => $date,
            'items' => array_map(fn ($a) => $this->payload($a), $items),
            'stats' => [
                'waiting' => $statuses->get('en_espera_triaje', 0),
                'in_triage' => $statuses->get('en_triaje', 0),
                'in_consult' => $statuses->get('en_atencion', 0),
                'attended' => $statuses->get('triaje_completado', 0) + $statuses->get('atendida', 0),
                'total' => count($items),
            ],
        ]);
    }

    /** POST /api/queue/{id}/send-triage · check-in presencial → turno + en_espera_triaje. */
    public function sendTriage(Request $request, string $id): JsonResponse
    {
        $appointment = $this->find($id);

        try {
            $appointment = $this->appointments->sendToTriage($appointment, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        QueueBroadcaster::dispatch($appointment);

        return $this->success($this->payload($appointment->fresh(['patient.user', 'doctor.user'])));
    }

    /** POST /api/queue/{id}/call-triage → en_triaje. */
    public function callTriage(Request $request, string $id): JsonResponse
    {
        $appointment = $this->find($id);

        try {
            $this->appointments->transition($appointment, AppointmentStatus::EnTriaje, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        QueueBroadcaster::dispatch($appointment);

        return $this->success($this->payload($appointment->fresh(['patient.user', 'doctor.user'])));
    }

    /** POST /api/queue/{id}/finish-triage → triaje_completado (variante desde el tablero). */
    public function finishTriage(Request $request, string $id): JsonResponse
    {
        $appointment = $this->find($id);

        try {
            $this->appointments->transition($appointment, AppointmentStatus::TriajeCompletado, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        QueueBroadcaster::dispatch($appointment);

        return $this->success($this->payload($appointment->fresh(['patient.user', 'doctor.user'])));
    }

    /** POST /api/queue/{id}/call-consult → en_atencion. */
    public function callConsult(Request $request, string $id): JsonResponse
    {
        $appointment = $this->find($id);

        try {
            $this->appointments->transition($appointment, AppointmentStatus::EnAtencion, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        QueueBroadcaster::dispatch($appointment);

        return $this->success($this->payload($appointment->fresh(['patient.user', 'doctor.user'])));
    }

    /** POST /api/queue/{id}/attended → atendida (sale de la cola). */
    public function attended(Request $request, string $id): JsonResponse
    {
        $appointment = $this->find($id);

        try {
            $this->appointments->transition($appointment, AppointmentStatus::Atendida, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        QueueBroadcaster::dispatch($appointment);

        return $this->success($this->payload($appointment->fresh(['patient.user', 'doctor.user'])));
    }

    /** GET /api/queue/stats-today · contadores para el header de la TV. */
    public function statsToday(Request $request): JsonResponse
    {
        if (! $this->authorizeTv($request)) {
            return $this->error('No autenticado', 401);
        }

        $date = now()->toDateString();
        $items = $this->appointments->queueOfDay($date);

        $statuses = collect($items)->groupBy(fn ($a) => $a->status->value)->map->count();
        $attended = Appointment::query()
            ->where('date', $date)
            ->whereIn('status', [AppointmentStatus::Atendida, AppointmentStatus::Documentada])
            ->count();

        return $this->success([
            'date' => $date,
            'waiting' => $statuses->get('en_espera_triaje', 0),
            'in_triage' => $statuses->get('en_triaje', 0),
            'in_consult' => $statuses->get('en_atencion', 0),
            'attended' => $attended,
            'total' => count($items) + $attended,
        ]);
    }

    private function find(string $id): Appointment
    {
        $appointment = Appointment::with(['patient.user', 'doctor.user', 'specialty', 'doctor.consultorio'])->find($id);

        if (! $appointment) {
            abort(404, 'Cita no encontrada');
        }

        return $appointment;
    }

    /** Auth de staff O token de TV (solo lectura). */
    private function authorizeTv(Request $request): bool
    {
        $user = $request->user();

        if ($user) {
            return in_array($user->role->value, ['recepcionista', 'enfermera', 'medico', 'administrador'], true);
        }

        $token = $request->query('tvToken');

        if (! $token) {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);

            return ($payload['aud'] ?? null) === 'tv' && ($payload['exp'] ?? 0) > now()->timestamp;
        } catch (\Throwable) {
            return false;
        }
    }

    private function payload(Appointment $appointment): array
    {
        $checkInAt = $appointment->check_in_time
            ? Carbon::parse($appointment->date->format('Y-m-d').' '.$appointment->check_in_time)
            : null;

        return [
            'id' => $appointment->id,
            'code' => $appointment->code,
            'turno' => $appointment->turno,
            'time' => $appointment->time,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'patient' => [
                'id' => $appointment->patient->id,
                'name' => $appointment->patient->user->name,
                'dni' => $appointment->patient->dni,
            ],
            'doctor' => [
                'id' => $appointment->doctor->id,
                'name' => $appointment->doctor->user->name,
                'initials' => $appointment->doctor->initials,
                'consultorio' => $appointment->doctor->consultorio?->nombre,
            ],
            'specialty' => $appointment->specialty?->name,
            'check_in_time' => $appointment->check_in_time,
            'wait_min' => $checkInAt ? max(0, (int) $checkInAt->diffInMinutes(now())) : null,
        ];
    }
}
