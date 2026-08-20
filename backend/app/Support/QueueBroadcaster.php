<?php

namespace App\Support;

use App\Events\QueueUpdated;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;

/**
 * Emisor de eventos de cola. Interino hasta la Parte 8 (Reverb):
 * si el broadcaster falla (sin servidor Reverb en dev), no rompe la request.
 */
final class QueueBroadcaster
{
    public static function dispatch(Appointment $appointment): void
    {
        try {
            event(new QueueUpdated($appointment));
        } catch (\Throwable $e) {
            Log::debug('queue.updated broadcast skipped', ['exception' => $e->getMessage()]);
        }
    }
}
