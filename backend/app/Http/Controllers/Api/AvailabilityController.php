<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\Doctor;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Disponibilidad pública: cruza plantillas con citas activas (BACKEND.md §5.4). */
class AvailabilityController extends ApiController
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function index(Request $request): JsonResponse
    {
        $specialtyId = $request->query('specialtyId');
        $from = Carbon::parse($request->query('from', now()->toDateString()));
        $to = Carbon::parse($request->query('to', now()->addDays(7)->toDateString()));

        if ($to->lt($from)) {
            return $this->error('El rango de fechas es inválido', 422);
        }

        $doctors = Doctor::with(['user', 'specialty'])
            ->whereHas('user', fn ($q) => $q->where('active', true))
            ->when($specialtyId, fn ($q) => $q->where('specialty_id', $specialtyId))
            ->get();

        $slots = collect();

        foreach ($doctors as $doctor) {
            foreach ($this->appointments->freeSlots($doctor, $from, $to) as $slot) {
                $slots->push([
                    'date' => $slot['date'],
                    'time' => $slot['time'],
                    'price' => $slot['price'],
                    'doctor' => [
                        'id' => $doctor->id,
                        'name' => $doctor->user->name,
                        'initials' => $doctor->initials,
                        'rating' => (float) $doctor->rating,
                    ],
                    'specialty' => [
                        'id' => $doctor->specialty->id,
                        'name' => $doctor->specialty->name,
                    ],
                ]);
            }
        }

        $slots = $slots->sortBy(fn ($s) => $s['date'].' '.$s['time'].' '.$s['doctor']['name'])->values()->all();

        return $this->success([
            'specialtyId' => $specialtyId,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'slots' => $slots,
        ]);
    }
}
