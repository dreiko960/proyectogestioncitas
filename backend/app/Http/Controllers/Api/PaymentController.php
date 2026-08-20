<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Enums\PaidType;
use App\Enums\PaymentMethod;
use App\Http\Controllers\ApiController;
use App\Http\Requests\CashPaymentRequest;
use App\Http\Requests\ChargePaymentRequest;
use App\Http\Requests\CompleteBalanceRequest;
use App\Http\Requests\VerifyPaymentRequest;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Pagos: caja + Culqi (BACKEND.md §5.6). */
class PaymentController extends ApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    /** POST /api/payments/charge · cobro Culqi desde el paciente. */
    public function charge(ChargePaymentRequest $request): JsonResponse
    {
        $appointment = $this->ownAppointment($request, $request->validated('appointmentId'));

        try {
            $payment = $this->payments->chargeAppointment(
                $appointment,
                PaidType::from($request->validated('type')),
                $request->validated('culqiToken'),
                $request->user(),
            );

            if (in_array($appointment->status, [AppointmentStatus::Agendada, AppointmentStatus::Confirmada], true)) {
                $appointment->status = AppointmentStatus::Pagada;
                $appointment->save();
            }
        } catch (\Throwable $e) {
            return $this->error('El pago no pudo completarse: '.$e->getMessage(), 402);
        }

        return $this->success($this->payload($payment->refresh()));
    }

    /** POST /api/payments/cash · cobro en caja con comprobante. */
    public function cash(CashPaymentRequest $request): JsonResponse
    {
        $appointment = Appointment::find($request->validated('appointmentId'));

        if (! $appointment) {
            return $this->error('Cita no encontrada', 404);
        }

        $payment = $this->payments->cash(
            $appointment,
            PaymentMethod::from($request->validated('method')),
            isset($request->validated()['type']) ? PaidType::from($request->validated('type')) : null,
            $request->user(),
        );

        return $this->success($this->payload($payment->refresh()));
    }

    /** POST /api/payments/verify · recepción confirma pago declarado por el paciente. */
    public function verify(VerifyPaymentRequest $request): JsonResponse
    {
        $payment = Payment::find($request->validated('paymentId'));

        if (! $payment) {
            return $this->error('Pago no encontrado', 404);
        }

        try {
            $payment = $this->payments->verify($payment, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->payload($payment->refresh()));
    }

    /** POST /api/payments/complete-balance · cobra el saldo del 50% en recepción. */
    public function completeBalance(CompleteBalanceRequest $request): JsonResponse
    {
        $appointment = Appointment::find($request->validated('appointmentId'));

        if (! $appointment) {
            return $this->error('Cita no encontrada', 404);
        }

        try {
            $payment = $this->payments->completeBalance(
                $appointment,
                PaymentMethod::from($request->validated('method')),
                $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->payload($payment->refresh()));
    }

    /** POST /api/payments/{id}/refund · reembolso Culqi (admin). */
    public function refund(Request $request, string $id): JsonResponse
    {
        $payment = Payment::find($id);

        if (! $payment) {
            return $this->error('Pago no encontrado', 404);
        }

        try {
            $payment = $this->payments->refund($payment, $request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->payload($payment->refresh()));
    }

    /** GET /api/payments/receipts/{id} · comprobante en PDF. */
    public function receipt(string $id): JsonResponse
    {
        $payment = Payment::with(['appointment.patient.user', 'appointment.doctor.user', 'appointment.specialty'])->find($id);

        if (! $payment) {
            return $this->error('Pago no encontrado', 404);
        }

        $pdf = $this->renderReceipt($payment);
        $fileName = 'comprobante-'.($payment->receipt_code ?? $payment->code).'.pdf';

        Storage::put("receipts/{$fileName}", $pdf);

        return $this->success([
            'receipt_code' => $payment->receipt_code,
            'payment' => $this->payload($payment),
            'url' => Storage::url("receipts/{$fileName}"),
        ]);
    }

    private function renderReceipt(Payment $payment): string
    {
        $html = view('pdfs.receipt', ['payment' => $payment])->render();

        return Pdf::loadHTML($html)->output();
    }

    private function ownAppointment(Request $request, string $id): Appointment
    {
        $appointment = Appointment::find($id);

        if (! $appointment) {
            abort(404, 'Cita no encontrada');
        }

        if ($appointment->patient->user_id !== $request->user()->id) {
            abort(403, 'No autorizado');
        }

        return $appointment;
    }

    private function payload(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'code' => $payment->code,
            'appointment_id' => $payment->appointment_id,
            'amount' => (float) $payment->amount,
            'method' => $payment->method->value,
            'status' => $payment->status->value,
            'paid_type' => $payment->paid_type->value,
            'receipt_code' => $payment->receipt_code,
            'gateway' => $payment->gateway,
            'culqi_order_id' => $payment->culqi_order_id,
            'culqi_charge_id' => $payment->culqi_charge_id,
            'refunded_at' => $payment->refunded_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }
}
