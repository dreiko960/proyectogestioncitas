<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cada transición de la cola emite este evento (BACKEND.md §5.8/§8.1).
 * El canal es el consultorio del médico de la cita.
 */
class QueueUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function broadcastOn(): Channel
    {
        $consultorioId = $this->appointment->doctor?->consultorio_id;

        return new Channel('queue.'.($consultorioId ?? 'general'));
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }
}
