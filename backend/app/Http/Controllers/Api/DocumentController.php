<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\Appointment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class DocumentController extends ApiController
{
    
    public function pdf(Request $request, string $id): JsonResponse
    {
        $appointment = Appointment::with(['patient.user', 'doctor.user', 'specialty', 'diagnosis', 'triage'])->find($id);

        if (! $appointment) {
            return $this->error('Cita no encontrada', 404);
        }

        $user = $request->user();
        $isOwner = $appointment->patient->user_id === $user->id;
        $isDoctor = $appointment->doctor->user_id === $user->id;

        if (! $isOwner && ! $isDoctor && $user->role->value !== 'administrador') {
            return $this->error('No autorizado', 403);
        }

        $html = view('pdfs.clinical', ['appointment' => $appointment])->render();
        $pdf = Pdf::loadHTML($html)->output();

        $path = 'clinical/'.$appointment->code.'.pdf';
        Storage::put($path, $pdf);

        return $this->success([
            'appointment_id' => $appointment->id,
            'path' => $path,
            'url' => Storage::url($path),
            'size' => strlen($pdf),
        ], 201);
    }
}
