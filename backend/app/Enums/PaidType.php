<?php

namespace App\Enums;

enum PaidType: string
{
    case Adelanto = 'adelanto';
    case Total = 'total';

    public function label(): string
    {
        return match ($this) {
            self::Adelanto => 'Adelanto (50%)',
            self::Total => 'Total (100%)',
        };
    }
}
