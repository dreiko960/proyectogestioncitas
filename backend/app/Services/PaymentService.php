<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AuditSev;
use App\Enums\PaidType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use App\Support\Codes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class PaymentService
{
    public function __construct(
        private readonly CulqiGateway $culqi,
        private readonly AuditService $audit,
    ) {}

    
    public function chargeAppointment(Appointment $appointment, PaidType $type, string $culqiToken, ?User $by = null): Payment
    {
        return DB::transaction(function () use ($appointment, $type, $culqiToken, $by) {
            $amount = $this->amountFor($appointment, $type);
            $order = $this->culqi->createOrder(round($amount * 100));
            $charge = $this->culqi->createCharge(round($amount * 100), $order['id'], $culqiToken);

            $payment = Payment::create([
                'id' => Str::uuid()->toString(),
                'code' => Codes::next('pagos', 'P'),
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'amount' => $amount,
                'method' => PaymentMethod::TarjetaPasarela,
                'status' => PaymentStatus::Pagado,
                'paid_type' => $type,
                'gateway' => true,
                'culqi_order_id' => $order['id'],
                'culqi_charge_id' => $charge['id'],
                'culqi_data' => $charge,
            ]);

            $appointment->paid_type = $type;
            $appointment->save();

            $this->audit->record($by, 'Pago Culqi', "Cita {$appointment->code} · {$type->value} · S/ {$amount}", AuditSev::Info);

            return $payment;
        });
    }

    
    public function cash(Appointment $appointment, PaymentMethod $method, ?PaidType $type = null, ?User $by = null, ?float $amountOverride = null): Payment
    {
        $type ??= $this->currentPaidType($appointment);

        return DB::transaction(function () use ($appointment, $method, $type, $by, $amountOverride) {
            $amount = $amountOverride ?? $this->amountFor($appointment, $type);
            $payment = Payment::create([
                'id' => Str::uuid()->toString(),
                'code' => Codes::next('pagos', 'P'),
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'amount' => $amount,
                'method' => $method,
                'status' => PaymentStatus::Pagado,
                'paid_type' => $type,
                'receipt_code' => Codes::next('pagos', 'R-'.now()->format('Y'), 'receipt_code'),
                'verified_by' => $by?->id,
            ]);

            $appointment->paid_type = $type;
            $appointment->save();

            $this->markAppointmentPaid($appointment, $payment);
            $this->audit->record($by, 'Pago en caja', "Cita {$appointment->code} · {$method->value} · S/ {$amount}", AuditSev::Info);

            return $payment;
        });
    }

    
    public function declared(Appointment $appointment, PaymentMethod $method, ?PaidType $type = null): Payment
    {
        $type ??= $this->currentPaidType($appointment);

        return Payment::create([
            'id' => Str::uuid()->toString(),
            'code' => Codes::next('pagos', 'P'),
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'amount' => $this->amountFor($appointment, $type),
            'method' => $method,
            'status' => PaymentStatus::PendienteVerificacion,
            'paid_type' => $type,
        ]);
    }

    
    public function verify(Payment $payment, ?User $by = null): Payment
    {
        if ($payment->status !== PaymentStatus::PendienteVerificacion) {
            throw new \InvalidArgumentException('El pago no está pendiente de verificación');
        }

        return DB::transaction(function () use ($payment, $by) {
            $payment->update([
                'status' => PaymentStatus::Pagado,
                'verified_by' => $by?->id,
                'receipt_code' => Codes::next('pagos', 'R-'.now()->format('Y'), 'receipt_code'),
            ]);

            $this->markAppointmentPaid($payment->appointment, $payment);

            $this->audit->record($by, 'Pago verificado', "{$payment->code} · {$payment->amount}", AuditSev::Info);

            return $payment->refresh();
        });
    }

    
    public function completeBalance(Appointment $appointment, PaymentMethod $method, ?User $by = null): Payment
    {
        if ($appointment->paid_type !== PaidType::Adelanto) {
            throw new \InvalidArgumentException('La cita no tiene saldo pendiente (adelanto 50%)');
        }

        $remaining = round((float) $appointment->specialty->price - $this->paidTotalOf($appointment), 2);
        $payment = $this->cash($appointment, $method, PaidType::Total, $by, $remaining);

        $this->audit->record($by, 'Saldo cobrado', "Cita {$appointment->code} · saldo S/ {$remaining}", AuditSev::Info);

        return $payment;
    }

    
    public function refund(Payment $payment, ?User $by = null): Payment
    {
        if ($payment->status !== PaymentStatus::Pagado || ! $payment->gateway) {
            throw new \InvalidArgumentException('Solo se reembolsan pagos Culqi en estado pagado');
        }

        return DB::transaction(function () use ($payment, $by) {
            $this->culqi->refund($payment->culqi_charge_id);

            $payment->update([
                'status' => PaymentStatus::Reembolsado,
                'refunded_at' => now(),
            ]);

            $this->audit->record($by, 'Reembolso Culqi', "{$payment->code} · {$payment->amount}", AuditSev::Warning);

            return $payment->refresh();
        });
    }

    
    public function amountFor(Appointment $appointment, PaidType $type): float
    {
        $price = (float) $appointment->specialty->price;

        return $type === PaidType::Adelanto ? round($price / 2, 2) : $price;
    }

    private function currentPaidType(Appointment $appointment): PaidType
    {
        return $appointment->paid_type ?? PaidType::Total;
    }

    private function markAppointmentPaid(Appointment $appointment, Payment $payment): void
    {
        if ($payment->paid_type === PaidType::Total
            && $appointment->payments()->where('status', PaymentStatus::Pagado)->where('paid_type', PaidType::Adelanto)->exists()) {
            $appointment->paid_type = PaidType::Total;
        } else {
            $appointment->paid_type = $payment->paid_type;
        }

        if (in_array($appointment->status, [AppointmentStatus::Agendada, AppointmentStatus::Confirmada], true)) {
            $appointment->status = AppointmentStatus::Pagada;
        }

        $appointment->save();
    }

    
    public function hasPendingBalance(Appointment $appointment): bool
    {
        return $appointment->paid_type === PaidType::Adelanto
            && ! $appointment->payments()->where('status', PaymentStatus::Pagado)->where('paid_type', PaidType::Total)->exists();
    }

    public function paidTotalOf(Appointment $appointment): float
    {
        return (float) $appointment->payments()
            ->where('status', PaymentStatus::Pagado)
            ->sum('amount');
    }
}
