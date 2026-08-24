<?php

namespace App\Http\Controllers\Api;

use App\Enums\WaitlistStatus;
use App\Http\Controllers\ApiController;
use App\Http\Requests\EnrollWaitlistRequest;
use App\Http\Requests\OfferWaitlistRequest;
use App\Models\WaitlistEntry;
use App\Services\WaitlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class WaitlistController extends ApiController
{
    public function __construct(private readonly WaitlistService $waitlist) {}

    
    public function store(EnrollWaitlistRequest $request): JsonResponse
    {
        $patient = $request->user()->patient;

        $entry = $this->waitlist->enroll(
            $patient,
            $request->validated('specialtyId'),
            $request->validated('doctorId'),
            $request->validated('preferred'),
        );

        return $this->success($this->payload($entry->load(['specialty', 'doctor.user'])), 201);
    }

    
    public function me(Request $request): JsonResponse
    {
        $entries = WaitlistEntry::with(['specialty', 'doctor.user'])
            ->where('patient_id', $request->user()->patient->id)
            ->orderByDesc('enrolled_at')
            ->get();

        return $this->success($entries->map(fn ($e) => $this->payload($e))->all());
    }

    
    public function confirm(Request $request, string $id): JsonResponse
    {
        $entry = $this->own($request, $id);

        try {
            $appointment = $this->waitlist->confirm($entry, $request->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 410);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'entry' => $this->payload($entry->refresh()),
            'appointment_id' => $appointment->id,
            'code' => $appointment->code,
        ]);
    }

    
    public function reject(Request $request, string $id): JsonResponse
    {
        $entry = $this->own($request, $id);

        try {
            $entry = $this->waitlist->reject($entry, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->payload($entry->refresh()));
    }

    
    public function offer(OfferWaitlistRequest $request, string $id): JsonResponse
    {
        $entry = WaitlistEntry::find($id);

        if (! $entry) {
            return $this->error('Inscripción no encontrada', 404);
        }

        try {
            $entry = $this->waitlist->offer($entry, $request->validated('date'), $request->validated('time'));
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->payload($entry->refresh()));
    }

    
    public function expire(Request $request, string $id): JsonResponse
    {
        $entry = WaitlistEntry::find($id);

        if (! $entry) {
            return $this->error('Inscripción no encontrada', 404);
        }

        try {
            $entry = $this->waitlist->expire($entry);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->payload($entry->refresh()));
    }

    private function own(Request $request, string $id): WaitlistEntry
    {
        $entry = WaitlistEntry::with(['specialty', 'doctor.user'])->find($id);

        if (! $entry) {
            abort(404, 'Inscripción no encontrada');
        }

        if ($entry->patient_id !== $request->user()->patient->id) {
            abort(403, 'No autorizado');
        }

        return $entry;
    }

    private function payload(WaitlistEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'code' => $entry->code,
            'position' => $entry->position,
            'status' => $entry->status->value,
            'preferred' => $entry->preferred,
            'specialty' => [
                'id' => $entry->specialty->id,
                'name' => $entry->specialty->name,
                'price' => (float) $entry->specialty->price,
            ],
            'doctor' => [
                'id' => $entry->doctor->id,
                'name' => $entry->doctor->user->name,
            ],
            'offer' => $entry->status === WaitlistStatus::Oferta ? [
                'date' => $entry->offer_date?->toDateString(),
                'time' => $entry->offer_time,
                'expires_at' => $entry->offer_expires_at?->toIso8601String(),
            ] : null,
            'created_appointment_id' => $entry->created_appointment_id,
            'enrolled_at' => $entry->enrolled_at?->toIso8601String(),
        ];
    }
}
