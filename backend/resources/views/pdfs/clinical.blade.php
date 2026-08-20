<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 16px; }
        .muted { color: #6b7280; font-size: 11px; }
        .row { padding: 4px 0; border-bottom: 1px solid #eee; }
        .label { color: #6b7280; display: inline-block; width: 160px; }
        h3 { margin: 16px 0 6px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SGCM-CMAS · Ficha de atención</h1>
        <div class="muted">{{ $appointment->code }} · {{ $appointment->date->format('d/m/Y') }} {{ $appointment->time }}</div>
    </div>

    <h3>Paciente</h3>
    <div class="row"><span class="label">Nombre</span>{{ $appointment->patient->user->name }}</div>
    <div class="row"><span class="label">DNI</span>{{ $appointment->patient->dni }}</div>
    <div class="row"><span class="label">Teléfono</span>{{ $appointment->patient->phone }}</div>

    <h3>Atención</h3>
    <div class="row"><span class="label">Médico</span>{{ $appointment->doctor->user->name }}</div>
    <div class="row"><span class="label">Especialidad</span>{{ $appointment->specialty->name }}</div>
    <div class="row"><span class="label">Motivo</span>{{ $appointment->reason }}</div>

    @if ($appointment->triage)
        <h3>Triaje</h3>
        <div class="row"><span class="label">P/A</span>{{ $appointment->triage->pa }}</div>
        <div class="row"><span class="label">Temperatura</span>{{ $appointment->triage->temp }} °C</div>
        <div class="row"><span class="label">F.C.</span>{{ $appointment->triage->fc }}</div>
        <div class="row"><span class="label">Peso</span>{{ $appointment->triage->peso }} kg</div>
        <div class="row"><span class="label">Talla</span>{{ $appointment->triage->talla }} m</div>
    @endif

    @if ($appointment->diagnosis)
        <h3>Diagnóstico</h3>
        <div class="row"><span class="label">Dx</span>{{ $appointment->diagnosis->dx }}</div>
        <div class="row"><span class="label">Notas</span>{{ $appointment->diagnosis->notes }}</div>
    @endif

    <p class="muted">Documento generado por el sistema SGCM-CMAS.</p>
</body>
</html>