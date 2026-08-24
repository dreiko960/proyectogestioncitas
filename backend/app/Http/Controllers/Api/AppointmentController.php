<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Exceptions\SlotConflictException;
use App\Http\Controllers\ApiController;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\SaveDiagnosisRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Support\QueueBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends ApiController
{
    public function __construct(private readonly AppointmentService $appointments) {}

    
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $patient = $this->resolvePatient($request);
        $data = $request->validated();

        if (isset($data['patientId']) && $request->user()->role === UserRole::Paciente) {
            return $this->error('Un paciente solo puede reservar para sí mismo', 403);
        }

        try {
            $appointment = $this->appointments->reserve($patient, $data, $request->user());
        } catch (SlotConflictException $e) {
            return $this->error($e->getMessage(), 409, ['alternatives' => $e->alternatives]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->payload($appointment->load(['patient.user', 'doctor.user', 'specialty', 'payments'])), 201);
    }

    
    public function me(Request $request): JsonResponse
    {
        $patient = $this->patientOf($request->user());
        $today = now()->toDateString();

        $upcoming = Appointment::with(['doctor.user', 'specialty'])
            ->where('patient_id', $patient->id)
            ->where('date', '>=', $today)
            ->whereNotIn('status', [AppointmentStatus::Cancelada])
            ->orderBy('date')->orderBy('time')
            ->get();

        $past = Appointment::with(['doctor.user', 'specialty'])
            ->where('patient_id', $patient->id)
            ->where(function ($q) use ($today) {
                $q->where('date', '<', $today)
                    ->orWhereIn('status', [AppointmentStatus::Atendida, AppointmentStatus::Documentada]);
            })
            ->whereNotIn('status', [AppointmentStatus::Cancelada])
            ->orderByDesc('date')->orderByDesc('time')
            ->get();

        $cancelled = Appointment::with(['doctor.user', 'specialty'])
            ->where('patient_id', $patient->id)
            ->where('status', AppointmentStatus::Cancelada)
            ->orderByDesc('date')->orderByDesc('time')
            ->get();

        return $this->success([
            'upcoming' => $upcoming->map(fn ($a) => $this->payload($a))->all(),
            'past' => $past->map(fn ($a) => $this->payload($a))->all(),
            'cancelled' => $cancelled->map(fn ($a) => $this->payload($a))->all(),
        ]);
    }

    
    public function day(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            return $this->error('Fecha inválida (Y-m-d)', 422);
        }

        $query = Appointment::with(['patient.user', 'doctor.user', 'specialty', 'payments'])
            ->where('date', $date)
            ->whereNotIn('status', [AppointmentStatus::Cancelada, AppointmentStatus::Reprogramada]);

        if ($request->filled('specialtyId')) {
            $query->where('specialty_id', $request->query('specialtyId'));
        }

        if ($request->filled('doctorId')) {
            $query->where('doctor_id', $request->query('doctorId'));
        }

        $appointments = $query->orderBy('time')->get();

        return $this->success([
            'date' => $date,
            'items' => $appointments->map(fn ($a) => $this->payload($a))->all(),
        ]);
    }

    
    public function show(Request $request, string $id): JsonResponse
    {
        $appointment = Appointment::with(['patient.user', 'doctor.user', 'specialty', 'payments', 'triage', 'diagnosis'])
            ->find($id);

        if (! $appointment) {
            return $this->error('Cita no encontrada', 404);
        }

        if (! $this->canView($request->user(), $appointment)) {
            return $this->error('No autorizado', 403);
        }

        return $this->success($this->payload($appointment, true));
    }

    
    public function diagnosis(SaveDiagnosisRequest $request, string $id): JsonResponse
    {
        $appointment = Appointment::with(['patient.user', 'doctor.user', 'specialty', 'payments', 'triage', 'diagnosis'])
            ->find($id);

        if (! $appointment) {
            return $this->error('Cita no encontrada', 404);
        }

        if (! $this->canView($request->user(), $appointment)) {
            return $this->error('No autorizado', 403);
        }

        try {
            $appointment = $this->appointments->saveDiagnosis(
                $appointment,
                $request->validated('dx'),
                $request->validated('notes'),
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        QueueBroadcaster::dispatch($appointment);

        return $this->success(
            $this->payload($appointment->fresh(['patient.user', 'doctor.user', 'specialty', 'payments', 'triage', 'diagnosis']), true),
            201,
        );
    }

    
    public function checkin(Request $request, string $id): JsonResponse
    {
        $appointment = $this->ownAppointment($request, $id);

        try {
            $appointment = $this->appointments->checkIn($appointment, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->payload($appointment->fresh(['doctor.user', 'specialty'])));
    }

    
    public function cancel(CancelAppointmentRequest $request, string $id): JsonResponse
    {
        $appointment = $this->resolveCancellable($request, $id);

        try {
            $result = $this->appointments->cancel($appointment, $request->validated('reason'), $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'appointment' => $this->payload($appointment->fresh(['doctor.user', 'specialty'])),
            'warning' => $result['warning'],
        ]);
    }

    
    public function reschedule(RescheduleAppointmentRequest $request, string $id): JsonResponse
    {
        $appointment = $this->resolveCancellable($request, $id);

        try {
            $appointment = $this->appointments->reschedule($appointment, $request->validated('date'), $request->validated('time'), $request->user());
        } catch (SlotConflictException $e) {
            return $this->error($e->getMessage(), 409, ['alternatives' => $e->alternatives]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->payload($appointment->fresh(['doctor.user', 'specialty'])));
    }

    
    public function patientHistory(Request $request, string $pid): JsonResponse
    {
        $patient = Patient::with('user')->find($pid);

        if (! $patient) {
            return $this->error('Paciente no encontrado', 404);
        }

        $appointments = Appointment::with(['doctor.user', 'specialty', 'payments', 'triage', 'diagnosis'])
            ->where('patient_id', $pid)
            ->orderByDesc('date')->orderByDesc('time')
            ->get();

        return $this->success([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->user->name,
                'dni' => $patient->dni,
            ],
            'appointments' => $appointments->map(fn ($a) => $this->payload($a, true))->all(),
        ]);
    }

    

    private function resolvePatient(Request $request): Patient
    {
        if ($request->user()->role === UserRole::Paciente) {
            return $this->patientOf($request->user());
        }

        return Patient::findOrFail($request->validated('patientId'));
    }

    private function patientOf($user): Patient
    {
        return Patient::where('user_id', $user->id)->firstOrFail();
    }

    private function ownAppointment(Request $request, string $id): Appointment
    {
        $appointment = Appointment::find($id);

        if (! $appointment) {
            abort(404, 'Cita no encontrada');
        }

        $patient = $this->patientOf($request->user());

        if ($appointment->patient_id !== $patient->id) {
            abort(403, 'No autorizado');
        }

        return $appointment;
    }

    private function resolveCancellable(Request $request, string $id): Appointment
    {
        $appointment = Appointment::find($id);

        if (! $appointment) {
            abort(404, 'Cita no encontrada');
        }

        if ($request->user()->role === UserRole::Paciente && $appointment->patient->user_id !== $request->user()->id) {
            abort(403, 'No autorizado');
        }

        return $appointment;
    }

    private function canView($user, Appointment $appointment): bool
    {
        if (in_array($user->role, [UserRole::Administrador, UserRole::Recepcionista], true)) {
            return true;
        }

        if ($user->role === UserRole::Paciente) {
            return $appointment->patient->user_id === $user->id;
        }

        if ($user->role === UserRole::Medico) {
            return $appointment->doctor->user_id === $user->id;
        }

        return false;
    }

    private function payload(Appointment $appointment, bool $detail = false): array
    {
        $data = [
            'id' => $appointment->id,
            'code' => $appointment->code,
            'date' => $appointment->date->toDateString(),
            'time' => $appointment->time,
            'duration_min' => $appointment->duration_min,
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'reason' => $appointment->reason,
            'turno' => $appointment->turno,
            'paid_type' => $appointment->paid_type?->value,
            'doctor' => $appointment->doctor ? [
                'id' => $appointment->doctor->id,
                'name' => $appointment->doctor->user->name ?? null,
                'initials' => $appointment->doctor->initials,
            ] : null,
            'specialty' => $appointment->specialty ? [
                'id' => $appointment->specialty->id,
                'name' => $appointment->specialty->name,
                'price' => (float) $appointment->specialty->price,
            ] : null,
        ];

        if ($detail) {
            $data['patient'] = $appointment->patient ? [
                'id' => $appointment->patient->id,
                'name' => $appointment->patient->user->name ?? null,
                'dni' => $appointment->patient->dni,
                'phone' => $appointment->patient->phone,
            ] : null;
            $data['payments'] = $appointment->payments->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'amount' => (float) $p->amount,
                'method' => $p->method->value,
                'status' => $p->status->value,
                'paid_type' => $p->paid_type->value,
                'receipt_code' => $p->receipt_code,
                'gateway' => $p->gateway,
            ])->all();
            $data['triage'] = $appointment->triage ? [
                'id' => $appointment->triage->id,
                'pa' => $appointment->triage->pa,
                'temp' => $appointment->triage->temp,
                'fc' => $appointment->triage->fc,
                'peso' => $appointment->triage->peso,
                'talla' => $appointment->triage->talla,
            ] : null;
            $data['diagnosis'] = $appointment->diagnosis ? [
                'id' => $appointment->diagnosis->id,
                'dx' => $appointment->diagnosis->dx,
                'notes' => $appointment->diagnosis->notes,
            ] : null;
        }

        return $data;
    }
}
