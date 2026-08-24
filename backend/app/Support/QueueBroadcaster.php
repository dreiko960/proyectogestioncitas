<?php

namespace App\Support;

use App\Events\QueueUpdated;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;


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
