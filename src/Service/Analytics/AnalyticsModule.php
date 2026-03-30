<?php

declare(strict_types=1);

namespace App\Service\Analytics;

final class AnalyticsModule
{
    const RECOVERY_INDEX    = 'recovery_index';
    const SLEEP_SCORING     = 'sleep_scoring';
    const FATIGUE_DETECTION = 'fatigue_detection';
    const BODY_MEASUREMENT  = 'body_measurement';
    const TRAINING_VOLUME   = 'training_volume';

    const ALL = [
        self::RECOVERY_INDEX,
        self::SLEEP_SCORING,
        self::FATIGUE_DETECTION,
        self::BODY_MEASUREMENT,
        self::TRAINING_VOLUME,
    ];
}
