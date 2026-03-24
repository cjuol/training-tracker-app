<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkoutStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En progreso',
            self::Completed => 'Completado',
        };
    }
}
