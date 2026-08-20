<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PendienteVerificacion = 'pendiente_verificacion';
    case Pagado = 'pagado';
    case Reembolsado = 'reembolsado';
    case Fallido = 'fallido';

    public function label(): string
    {
        return match ($this) {
            self::PendienteVerificacion => 'Pendiente de verificación',
            self::Pagado => 'Pagado',
            self::Reembolsado => 'Reembolsado',
            self::Fallido => 'Fallido',
        };
    }
}
