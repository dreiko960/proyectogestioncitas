<?php

namespace App\Enums;

enum UserRole: string
{
    case Paciente = 'paciente';
    case Medico = 'medico';
    case Enfermera = 'enfermera';
    case Recepcionista = 'recepcionista';
    case Administrador = 'administrador';

    public function label(): string
    {
        return match ($this) {
            self::Paciente => 'Paciente',
            self::Medico => 'Médico',
            self::Enfermera => 'Enfermera',
            self::Recepcionista => 'Recepcionista',
            self::Administrador => 'Administrador',
        };
    }

    public function ability(): string
    {
        return 'role:'.$this->value;
    }
}
