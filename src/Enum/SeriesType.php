<?php

declare(strict_types=1);

namespace App\Enum;

enum SeriesType: string
{
    case NormalTs = 'normal_ts';
    case Amrap = 'amrap';
    case Complex = 'complex';
    case Superseries = 'superseries';

    public function label(): string
    {
        return match ($this) {
            self::NormalTs => 'Serie Normal / TS',
            self::Amrap => 'AMRAP',
            self::Complex => 'Serie Compleja',
            self::Superseries => 'Superserie',
        };
    }

    /**
     * Returns true if this is a superseries type, meaning exercises are grouped and
     * unlocked simultaneously (currentExerciseId = null) rather than sequentially locked.
     */
    public function isSuperseriesType(): bool
    {
        return self::Superseries === $this;
    }
}
