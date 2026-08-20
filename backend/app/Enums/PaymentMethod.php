<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Efectivo = 'efectivo';
    case Yape = 'yape';
    case Plin = 'plin';
    case Transferencia = 'transferencia';
    case TarjetaPasarela = 'tarjeta_pasarela';

    public function label(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Yape => 'Yape',
            self::Plin => 'Plin',
            self::Transferencia => 'Transferencia',
            self::TarjetaPasarela => 'Tarjeta (pasarela)',
        };
    }
}
