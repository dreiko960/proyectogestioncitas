<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pasarela Culqi v2 (BACKEND.md §7.1). En local sin CULQI_API_KEY usa un modo
 * simulado (prefijo `mock_`) para que el flujo de pago sea testeable sin credenciales.
 */
class CulqiGateway
{
    public function configured(): bool
    {
        $key = (string) config('services.culqi.api_key');

        return $key !== '' && ! str_contains($key, '...');
    }

    public function createOrder(int $amountInCents, string $currency = 'PEN'): array
    {
        if (! $this->configured()) {
            return $this->mock('order');
        }

        $res = Http::withToken(config('services.culqi.api_key'))
            ->post(config('services.culqi.base_url').'/orders', [
                'amount' => $amountInCents,
                'currency_code' => $currency,
                'description' => 'SGCM-CMAS consulta médica',
                'metadata' => ['source' => 'sgcm-cmas'],
            ])->throw();

        return $res->json();
    }

    public function createCharge(int $amountInCents, string $orderId, string $token): array
    {
        if (! $this->configured()) {
            return $this->mock('charge');
        }

        $res = Http::withToken(config('services.culqi.api_key'))
            ->post(config('services.culqi.base_url').'/charges', [
                'amount' => $amountInCents,
                'currency_code' => 'PEN',
                'email' => 'paciente@sgcm.local',
                'source_id' => $token,
                'order_id' => $orderId,
            ])->throw();

        return $res->json();
    }

    public function refund(string $chargeId): array
    {
        if (! $this->configured()) {
            return $this->mock('refund');
        }

        $res = Http::withToken(config('services.culqi.api_key'))
            ->post(config('services.culqi.base_url')."/charges/{$chargeId}/refunds")
            ->throw();

        return $res->json();
    }

    private function mock(string $kind): array
    {
        $suffix = substr(Str::uuid()->toString(), 0, 18);

        return match ($kind) {
            'order' => [
                'id' => 'mock_order_'.$suffix,
                'amount' => 0,
                'status' => 'created',
            ],
            'charge' => [
                'id' => 'mock_charge_'.$suffix,
                'status' => 'authorized',
            ],
            'refund' => [
                'id' => 'mock_refund_'.$suffix,
                'status' => 'approved',
            ],
        };
    }
}
