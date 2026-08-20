<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\ApiController;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Webhook de Culqi (BACKEND.md §7.2): público, firma HMAC v2, idempotente. */
class WebhookController extends ApiController
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        $event = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        $orderId = $data['order_id'] ?? $data['metadata']['order_id'] ?? null;
        $chargeId = $data['id'] ?? null;

        $payment = $orderId
            ? Payment::query()->where('culqi_order_id', $orderId)->first()
            : null;

        if (! $payment) {
            return $this->success(['received' => true, 'ignored' => true, 'reason' => 'order_not_found']);
        }

        if ($chargeId) {
            $payment->culqi_charge_id = $chargeId;
        }
        $payment->culqi_data = $payload;

        if ($event === 'charge.succeeded') {
            $payment->status = PaymentStatus::Pagado;
            $payment->save();

            return $this->success(['received' => true, 'updated' => true]);
        }

        if ($event === 'charge.refunded') {
            $payment->status = PaymentStatus::Reembolsado;
            $payment->refunded_at = now();
            $payment->save();

            return $this->success(['received' => true, 'updated' => true]);
        }

        $payment->save();

        return $this->success(['received' => true, 'updated' => false, 'reason' => 'event_not_handled']);
    }
}
