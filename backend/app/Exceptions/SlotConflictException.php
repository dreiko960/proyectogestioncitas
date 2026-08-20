<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Conflicto de horario al reservar: el slot ya no está disponible.
 * El controlador responde 409 con `alternatives[]` (mismo comportamiento del prototipo).
 */
class SlotConflictException extends RuntimeException
{
    public function __construct(
        public readonly array $alternatives = [],
        string $message = 'Este horario ya no está disponible',
    ) {
        parent::__construct($message);
    }
}
