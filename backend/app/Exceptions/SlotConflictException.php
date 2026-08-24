<?php

namespace App\Exceptions;

use RuntimeException;


class SlotConflictException extends RuntimeException
{
    public function __construct(
        public readonly array $alternatives = [],
        string $message = 'Este horario ya no está disponible',
    ) {
        parent::__construct($message);
    }
}
