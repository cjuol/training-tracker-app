<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use App\DTO\Analytics\AnalyticsVerdict;
use App\Entity\DailyHeartRate;
use App\Entity\User;
use App\Repository\DailyHeartRateRepository;
use Cjuol\StatGuard\RobustStats;

class RecoveryIndexService
{
    private const MIN_DATA_POINTS = 5;

    public function __construct(
        private readonly DailyHeartRateRepository $heartRateRepo,
    ) {}

    public function compute(User $user, int $days = 30): AnalyticsVerdict
    {
        $records = $this->heartRateRepo->findRecentByUser($user, $days);

        $rhrValues = array_values(array_filter(
            array_map(fn(DailyHeartRate $r) => $r->getRestingHeartRate(), $records),
            fn(?int $v) => $v !== null,
        ));

        if (count($rhrValues) < self::MIN_DATA_POINTS) {
            return AnalyticsVerdict::insufficientData(AnalyticsModule::RECOVERY_INDEX);
        }

        $floatValues = array_map('floatval', $rhrValues);

        $robust = new RobustStats();
        $cv     = $robust->getCoefficientOfVariation($floatValues); // returns percentage (0–100)
        $mad    = $robust->getMad($floatValues);
        $mean   = $robust->getHuberMean($floatValues);
        $outlierValues = $robust->getOutliers($floatValues);

        // CV thresholds: < 5% → STABLE, 5–10% → CAUTION, > 10% → ALERT
        [$verdict, $score, $insights] = match (true) {
            $cv < 5.0 => [
                'STABLE',
                round(100.0 - $cv * 2, 1),
                [sprintf('Frecuencia cardíaca en reposo muy estable (CV %.1f%%). La recuperación es óptima.', $cv)],
            ],
            $cv < 10.0 => [
                'CAUTION',
                round(100.0 - $cv * 4, 1),
                [sprintf('Variabilidad moderada en FC reposo (CV %.1f%%). Monitorear la recuperación.', $cv)],
            ],
            default => [
                'ALERT',
                round(max(0.0, 60.0 - $cv * 2), 1),
                [sprintf('Alta variabilidad en FC reposo (CV %.1f%%). Posible fatiga acumulada — considerar reducir la carga.', $cv)],
            ],
        };

        if (count($outlierValues) > 0) {
            $insights[] = sprintf(
                '%d día(s) con FC reposo anómala detectada(s) — pueden indicar enfermedad o estrés puntual.',
                count($outlierValues),
            );
        }

        return new AnalyticsVerdict(
            module: AnalyticsModule::RECOVERY_INDEX,
            verdict: $verdict,
            score: $score,
            insights: $insights,
            outliers: $outlierValues,
            computedAt: new \DateTimeImmutable(),
        );
    }
}
