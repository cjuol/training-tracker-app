<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use App\DTO\Analytics\AnalyticsVerdict;
use App\Entity\BodyMeasurement;
use App\Entity\User;
use App\Repository\BodyMeasurementRepository;
use Cjuol\StatGuard\RobustStats;
use Cjuol\StatGuard\StatsComparator;

class BodyMeasurementAnalyzerService
{
    private const MIN_DATA_POINTS = 5;

    public function __construct(
        private readonly BodyMeasurementRepository $bodyRepo,
    ) {}

    public function compute(User $user): AnalyticsVerdict
    {
        // findLast10ByAthlete returns DESC; reverse to get chronological ASC order
        $measurements = array_reverse($this->bodyRepo->findLast10ByAthlete($user));

        $weights = array_values(array_filter(
            array_map(fn(BodyMeasurement $m) => $m->getWeightKg(), $measurements),
            fn(?float $v) => $v !== null,
        ));

        if (count($weights) < self::MIN_DATA_POINTS) {
            return AnalyticsVerdict::insufficientData(AnalyticsModule::BODY_MEASUREMENT);
        }

        $robust  = new RobustStats();
        $outliers = $robust->getOutliers($weights);
        $huberMean = $robust->getHuberMean($weights);
        $ci        = $robust->getConfidenceIntervals($weights);

        $comparator = new StatsComparator();
        $analysis   = $comparator->analyze($weights);
        $trendVerdict = $analysis['verdict'];

        // Map StatsComparator verdict to AnalyticsVerdict verdict
        $verdict = match (true) {
            str_contains($trendVerdict, 'ALERT')  => 'ALERT',
            str_contains($trendVerdict, 'CAUTION') => 'CAUTION',
            default                                => 'STABLE',
        };

        // Determine simple trend direction from first half vs second half medians
        $half  = (int) ceil(count($weights) / 2);
        $first = array_slice($weights, 0, $half);
        $second = array_slice($weights, $half);

        $firstMedian  = $robust->getMedian($first);
        $secondMedian = count($second) > 0 ? $robust->getMedian($second) : $firstMedian;

        $trendLabel = match (true) {
            $secondMedian > $firstMedian + 0.5 => 'ascendente',
            $secondMedian < $firstMedian - 0.5 => 'descendente',
            default                            => 'estable',
        };

        $score = match ($verdict) {
            'STABLE'  => 80.0,
            'CAUTION' => 55.0,
            default   => 30.0,
        };

        $insights = [
            sprintf('Peso de tendencia robusta (Huber): %.1f kg.', $huberMean),
            sprintf('Rango de confianza: %.1f – %.1f kg.', $ci['lower'], $ci['upper']),
            sprintf('Tendencia en el período: %s.', $trendLabel),
        ];

        if (count($outliers) > 0) {
            $insights[] = sprintf(
                '%d medición(es) sospechosa(s) detectada(s) — posibles errores de registro o fluctuaciones puntuales.',
                count($outliers),
            );
        }

        return new AnalyticsVerdict(
            module: AnalyticsModule::BODY_MEASUREMENT,
            verdict: $verdict,
            score: $score,
            insights: $insights,
            outliers: $outliers,
            computedAt: new \DateTimeImmutable(),
        );
    }
}
