<?php

declare(strict_types=1);

namespace App\Enum;

enum MeasurementType: string
{
    case RepsWeight = 'reps_weight';
    case TimeDistance = 'time_distance';
    case TimeKcal = 'time_kcal';

    public function label(): string
    {
        return match ($this) {
            self::RepsWeight => 'Repeticiones / Peso',
            self::TimeDistance => 'Tiempo / Distancia',
            self::TimeKcal => 'Tiempo / Kcal',
        };
    }
}
