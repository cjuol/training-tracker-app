<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use App\DTO\Analytics\AnalyticsVerdict;
use App\Entity\SleepLog;
use App\Entity\User;
use App\Repository\SleepLogRepository;
use Cjuol\StatGuard\RobustStats;
use Cjuol\StatGuard\StatsComparator;

class SleepScoringService
{
    private const MIN_DATA_POINTS = 5;
    /** Optimal sleep target in minutes (7.5h) */
    private const OPTIMAL_MINUTES = 450;

    public function __construct(
        private readonly SleepLogRepository $sleepRepo,
    ) {}

    public function compute(User $user, int $days = 14): AnalyticsVerdict
    {
        $records = $this->sleepRepo->findRecentByUser($user, $days);

        if (count($records) < self::MIN_DATA_POINTS) {
            return AnalyticsVerdict::insufficientData(AnalyticsModule::SLEEP_SCORING);
        }

        // Order ASC so "tonight" = last element
        $records = array_reverse($records);

        $durations   = array_map(fn(SleepLog $s) => (float) $s->getDurationMinutes(), $records);
        $tonight     = end($durations);

        $robust    = new RobustStats();
        $baseline  = $robust->getHuberMean($durations);
        $mad       = $robust->getMad($durations);
        $outliers  = $robust->getOutliers($durations);

        // Duration score: penalise deviation from optimal 7.5 h (450 min), capped 0–100
        // Each minute deviation from 450 costs 100/450 ≈ 0.222 points; but we use /4.5 for gentler slope
        $durationScore = max(0.0, 100.0 - abs($tonight - self::OPTIMAL_MINUTES) / 4.5);

        // Efficiency score: if available on tonight's record, use it (0–100); else skip
        $tonightRecord  = end($records);
        $efficiency     = $tonightRecord->getEfficiency(); // ?int, 0–100

        if ($efficiency !== null) {
            // Spec: 60% HR/efficiency component + 40% duration component
            $combinedScore = round(0.6 * $efficiency + 0.4 * $durationScore, 1);
        } else {
            // No efficiency data: duration is 100% weight
            $combinedScore = round($durationScore, 1);
        }

        // Deviation from personal baseline (in MAD units)
        $deviation = ($mad > 0) ? round(abs($tonight - $baseline) / $mad, 2) : 0.0;

        // Trend via StatsComparator
        $comparator  = new StatsComparator();
        $analysis    = $comparator->analyze($durations);
        $trendVerdict = $analysis['verdict'];

        // Map combined score to verdict
        $verdict = match (true) {
            $combinedScore >= 70 => 'STABLE',
            $combinedScore >= 50 => 'CAUTION',
            default              => 'ALERT',
        };

        $insights = [];

        if ($combinedScore >= 70) {
            $insights[] = sprintf('Sueño de buena calidad esta noche (%.0f min, puntuación %.1f).', $tonight, $combinedScore);
        } elseif ($combinedScore >= 50) {
            $insights[] = sprintf('Calidad de sueño moderada esta noche (%.0f min, puntuación %.1f). Intenta dormir más cerca de %.0f minutos.', $tonight, $combinedScore, self::OPTIMAL_MINUTES);
        } else {
            $insights[] = sprintf('Sueño deficiente esta noche (%.0f min, puntuación %.1f). Revisa tus hábitos de sueño.', $tonight, $combinedScore);
        }

        if ($deviation > 1.5) {
            $insights[] = sprintf('Duración %.0f min se aleja notablemente de tu media robusta (%.0f min).', $tonight, $baseline);
        }

        if (count($outliers) > 0) {
            $insights[] = sprintf('%d noche(s) con duración anómala detectada(s) en el período.', count($outliers));
        }

        return new AnalyticsVerdict(
            module: AnalyticsModule::SLEEP_SCORING,
            verdict: $verdict,
            score: $combinedScore,
            insights: $insights,
            outliers: $outliers,
            computedAt: new \DateTimeImmutable(),
        );
    }
}
