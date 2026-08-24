<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\StoreDoctorExceptionRequest;
use App\Http\Requests\StoreDoctorScheduleRequest;
use App\Models\Doctor;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class DoctorController extends ApiController
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function index(Request $request): JsonResponse
    {
        $doctors = Doctor::with(['user', 'specialty', 'consultorio'])
            ->whereHas('user', fn ($q) => $q->where('active', true))
            ->when($request->filled('specialtyId'), fn ($q) => $q->where('specialty_id', $request->query('specialtyId')))
            ->orderBy('rating', 'desc')
            ->get();

        return $this->success($doctors->map(fn ($d) => $this->payload($d))->all());
    }

    public function show(string $id): JsonResponse
    {
        $doctor = Doctor::with(['user', 'specialty', 'consultorio'])->find($id);

        if (! $doctor || ! $doctor->user->active) {
            return $this->error('Médico no encontrado', 404);
        }

        return $this->success($this->payload($doctor));
    }

    
    public function slots(Request $request, string $id): JsonResponse
    {
        $doctor = Doctor::with(['specialty', 'schedules', 'exceptions'])->find($id);

        if (! $doctor) {
            return $this->error('Médico no encontrado', 404);
        }

        $from = Carbon::parse($request->query('from', now()->toDateString()));
        $to = Carbon::parse($request->query('to', now()->addDays(7)->toDateString()));

        $key = "doctor_slots:{$doctor->id}:{$from->toDateString()}:{$to->toDateString()}";

        $slots = Cache::remember($key, 30, fn () => $this->appointments->freeSlots($doctor, $from, $to));

        return $this->success([
            'doctorId' => $doctor->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'slots' => $slots,
        ]);
    }

    
    public function storeSchedules(StoreDoctorScheduleRequest $request, string $id): JsonResponse
    {
        $doctor = $this->resolvableDoctor($request, $id);

        $doctor->schedules()->delete();
        $doctor->schedules()->createMany($request->validated('schedules'));

        return $this->success([
            'doctorId' => $doctor->id,
            'schedules' => $doctor->schedules()->orderBy('day_of_week')->orderBy('start_time')->get()->map(fn ($s) => [
                'day_of_week' => $s->day_of_week,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ])->all(),
        ], 201);
    }

    
    public function storeException(StoreDoctorExceptionRequest $request, string $id): JsonResponse
    {
        $doctor = $this->resolvableDoctor($request, $id);

        $exception = $doctor->exceptions()->updateOrCreate(
            ['date' => $request->validated('date')],
            ['reason' => $request->validated('reason')],
        );

        return $this->success([
            'doctorId' => $doctor->id,
            'date' => $exception->date->toDateString(),
            'reason' => $exception->reason,
        ], 201);
    }

    private function resolvableDoctor(Request $request, string $id): Doctor
    {
        $doctor = Doctor::find($id);

        if (! $doctor) {
            abort(404, 'Médico no encontrado');
        }

        if ($request->user()->role->value === 'medico' && $doctor->user_id !== $request->user()->id) {
            abort(403, 'Solo puedes editar tu propia agenda');
        }

        return $doctor;
    }

    private function payload(Doctor $doctor): array
    {
        return [
            'id' => $doctor->id,
            'name' => $doctor->user->name,
            'initials' => $doctor->initials,
            'phone' => $doctor->phone,
            'rating' => (float) $doctor->rating,
            'rating_count' => $doctor->rating_count,
            'bio' => $doctor->bio,
            'studies' => $doctor->studies,
            'exp' => $doctor->exp,
            'specialty' => $doctor->specialty ? [
                'id' => $doctor->specialty->id,
                'name' => $doctor->specialty->name,
                'price' => (float) $doctor->specialty->price,
            ] : null,
            'consultorio' => $doctor->consultorio ? [
                'id' => $doctor->consultorio->id,
                'nombre' => $doctor->consultorio->nombre,
                'piso' => $doctor->consultorio->piso,
            ] : null,
        ];
    }
}
