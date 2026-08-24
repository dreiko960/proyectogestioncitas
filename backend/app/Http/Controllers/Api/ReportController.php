<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\ApiController;
use App\Models\Appointment;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ReportController extends ApiController
{
    
    public function summary(Request $request): JsonResponse
    {
        $month = $request->query('month', now()->format('Y-m'));
        $from = Carbon::parse($month.'-01')->startOfDay();
        $to = $from->copy()->endOfMonth()->endOfDay();

        $appointments = Appointment::query()->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
        $total = (clone $appointments)->count();
        $cancelled = (clone $appointments)->where('status', AppointmentStatus::Cancelada)->count();
        $attended = (clone $appointments)->whereIn('status', [AppointmentStatus::Atendida, AppointmentStatus::Documentada])->count();

        $income = Payment::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', PaymentStatus::Pagado)
            ->sum('amount');

        return $this->success([
            'month' => $month,
            'appointments' => [
                'total' => $total,
                'cancelled' => $cancelled,
                'attended' => $attended,
                'cancel_rate' => $total > 0 ? round($cancelled / $total * 100, 1) : 0,
                'no_show_rate' => $total > 0 ? round(($total - $attended - $cancelled) / $total * 100, 1) : 0,
            ],
            'income' => (float) $income,
        ]);
    }

    
    public function occupancy(Request $request): JsonResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->query('to', now()->endOfMonth()->toDateString()));

        $rows = Appointment::query()
            ->with('specialty')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [AppointmentStatus::Cancelada, AppointmentStatus::Reprogramada])
            ->get();

        $bySpecialty = $rows->groupBy(fn ($a) => $a->specialty->name ?? 'Sin especialidad')
            ->map(fn ($g) => count($g))
            ->sortDesc();

        $byWeek = $rows->groupBy(fn ($a) => $a->date->format('o-W'))
            ->map(fn ($g) => count($g))
            ->sortKeys();

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'by_specialty' => $bySpecialty->all(),
            'weekly_trend' => $byWeek->all(),
            'total' => count($rows),
        ]);
    }

    
    public function export(Request $request): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse($request->query('to', now()->endOfMonth()->toDateString()));

        $appointments = Appointment::with(['patient.user', 'doctor.user', 'specialty'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')->orderBy('time')
            ->get();

        $headers = ['code', 'fecha', 'hora', 'paciente', 'dni', 'medico', 'especialidad', 'estado', 'turno'];

        return response()->streamDownload(function () use ($appointments, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            foreach ($appointments as $a) {
                fputcsv($out, [
                    $a->code,
                    $a->date->toDateString(),
                    $a->time,
                    $a->patient?->user?->name,
                    $a->patient?->dni,
                    $a->doctor?->user?->name,
                    $a->specialty?->name,
                    $a->status->value,
                    $a->turno,
                ]);
            }

            fclose($out);
        }, "citas-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv']);
    }
}
