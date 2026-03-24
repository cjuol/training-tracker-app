<?php

declare(strict_types=1);

namespace App\Enum;

enum AssignmentStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Completed => 'Completado',
            self::Paused => 'Pausado',
            self::Cancelled => 'Cancelado',
        };
    }
}
