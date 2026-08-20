<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Agendada = 'agendada';
    case Confirmada = 'confirmada';
    case Pagada = 'pagada';
    case CheckIn = 'check_in';
    case EnEsperaTriaje = 'en_espera_triaje';
    case EnTriaje = 'en_triaje';
    case TriajeCompletado = 'triaje_completado';
    case EnAtencion = 'en_atencion';
    case Atendida = 'atendida';
    case Documentada = 'documentada';
    case Cancelada = 'cancelada';
    case Reprogramada = 'reprogramada';

    public function label(): string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::Confirmada => 'Confirmada',
            self::Pagada => 'Pagada',
            self::CheckIn => 'Check-in',
            self::EnEsperaTriaje => 'En espera de triaje',
            self::EnTriaje => 'En triaje',
            self::TriajeCompletado => 'Triaje completado',
            self::EnAtencion => 'En atención',
            self::Atendida => 'Atendida',
            self::Documentada => 'Documentada',
            self::Cancelada => 'Cancelada',
            self::Reprogramada => 'Reprogramada',
        };
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Cancelada, self::Reprogramada], true);
    }
}
