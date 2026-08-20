<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 16px; }
        .muted { color: #6b7280; font-size: 11px; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #eee; }
        .label { color: #6b7280; }
        .total { font-size: 16px; font-weight: bold; margin-top: 12px; }
        .badge { font-weight: bold; color: #2563eb; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SGCM-CMAS · Comprobante de pago</h1>
        <div class="muted">{{ $payment->receipt_code ?? $payment->code }} · {{ $payment->created_at?->format('d/m/Y H:i') }}</div>
    </div>

    <div class="row"><span class="label">Paciente</span><span>{{ $payment->appointment->patient->user->name }}</span></div>
    <div class="row"><span class="label">DNI</span><span>{{ $payment->appointment->patient->dni }}</span></div>
    <div class="row"><span class="label">Médico</span><span>{{ $payment->appointment->doctor->user->name }}</span></div>
    <div class="row"><span class="label">Especialidad</span><span>{{ $payment->appointment->specialty->name }}</span></div>
    <div class="row"><span class="label">Cita</span><span>{{ $payment->appointment->date->format('d/m/Y') }} {{ $payment->appointment->time }} · {{ $payment->appointment->code }}</span></div>
    <div class="row"><span class="label">Método</span><span class="badge">{{ $payment->method->label() }}</span></div>
    <div class="row"><span class="label">Tipo</span><span>{{ $payment->paid_type->label() }}</span></div>
    <div class="row"><span class="label">Estado</span><span>{{ $payment->status->label() }}</span></div>

    <div class="total">Total: S/ {{ number_format((float) $payment->amount, 2) }}</div>

    <p class="muted">Comprobante generado por el sistema SGCM-CMAS.</p>
</body>
</html>