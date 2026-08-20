<?php

namespace App\Enums;

enum WaitlistStatus: string
{
    case EnEspera = 'en_espera';
    case Oferta = 'oferta';
    case Confirmada = 'confirmada';
    case Expirada = 'expirada';
    case Retirada = 'retirada';

    public function label(): string
    {
        return match ($this) {
            self::EnEspera => 'En espera',
            self::Oferta => 'Oferta',
            self::Confirmada => 'Confirmada',
            self::Expirada => 'Expirada',
            self::Retirada => 'Retirada',
        };
    }
}
