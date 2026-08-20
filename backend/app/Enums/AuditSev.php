<?php

namespace App\Enums;

enum AuditSev: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Advertencia',
            self::Danger => 'Peligro',
        };
    }
}
