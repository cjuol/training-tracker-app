<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use App\DTO\Analytics\AnalyticsVerdict;
use App\Entity\Exercise;
use App\Entity\User;
use App\Repository\SetLogRepository;
use Cjuol\StatGuard\ClassicStats;
use Cjuol\StatGuard\RobustStats;
use Cjuol\StatGuard\StatsComparator;

class FatigueDetectionService
{
    private const MIN_DATA_POINTS = 5;

    public function __construct(
        private readonly SetLogRepository $setLogRepo,
    ) {}

    public function computeForExercise(User $user, Exercise $exercise, int $weeks = 8): AnalyticsVerdict
    {
        $from = new \DateTimeImmutable('-' . $weeks . ' weeks');
        $to   = new \DateTimeImmutable();

        $rows  = $this->setLogRepo->findEffectiveLoadByExercise($user, $exercise, $from, $to);
        $loads = array_map(fn(array $r) => (float) $r['load'], $rows);

        if (count($loads) < self::MIN_DATA_POINTS) {
            return AnalyticsVerdict::insufficientData(AnalyticsModule::FATIGUE_DETECTION);
        }

        $classic   = new ClassicStats();
        $robust    = new RobustStats();
        $comparator = new StatsComparator();

        $classicMean = $classic->getMean($loads);
        $huberMean   = $robust->getHuberMean($loads);
        $mad         = $robust->getMad($loads);
        $outliers    = $robust->getOutliers($loads);
        $divergence  = $classicMean - $huberMean;

        // Positive divergence = 1-2 exceptional sessions inflate classic mean = masked fatigue
        $analysis    = $comparator->analyze($loads);
        $trendString = $analysis['verdict'];

        // Flag as ALERT when divergence > 1 MAD or comparator signals ALERT
        $isMaskedFatigue = $divergence > $mad || str_contains($trendString, 'ALERT');
        $isModerate      = $divergence > ($mad * 0.5) || str_contains($trendString, 'CAUTION');

        [$verdict, $score] = match (true) {
            $isMaskedFatigue => ['ALERT', max(0.0, round(50.0 - $divergence / max($mad, 1) * 10, 1))],
            $isModerate      => ['CAUTION', 60.0],
            default          => ['STABLE', 85.0],
        };

        $insights = [];

        if ($isMaskedFatigue) {
            $insights[] = sprintf(
                'Fatiga enmascarada detectada en "%s": media clásica (%.1f kg) >> media robusta (%.1f kg). '
                . 'Unas pocas sesiones excepcionales ocultan el verdadero rendimiento.',
                $exercise->getName(),
                $classicMean,
                $huberMean,
            );
        } elseif ($isModerate) {
            $insights[] = sprintf(
                'Ligera divergencia entre media clásica (%.1f kg) y robusta (%.1f kg) en "%s". Vigilar la evolución.',
                $classicMean,
                $huberMean,
                $exercise->getName(),
            );
        } else {
            $insights[] = sprintf(
                'Carga estable en "%s": media clásica (%.1f kg) y robusta (%.1f kg) son consistentes.',
                $exercise->getName(),
                $classicMean,
                $huberMean,
            );
        }

        if (count($outliers) > 0) {
            $insights[] = sprintf('%d sesión(es) con carga anómala detectada(s).', count($outliers));
        }

        return new AnalyticsVerdict(
            module: AnalyticsModule::FATIGUE_DETECTION,
            verdict: $verdict,
            score: $score,
            insights: $insights,
            outliers: $outliers,
            computedAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Aggregates fatigue across all exercises the user has logged.
     * Returns the worst verdict found (ALERT > CAUTION > STABLE > INSUFFICIENT_DATA).
     */
    public function computeForUser(User $user): AnalyticsVerdict
    {
        $exerciseRows = $this->setLogRepo->findExercisesLoggedByUser($user);

        if (empty($exerciseRows)) {
            return AnalyticsVerdict::insufficientData(AnalyticsModule::FATIGUE_DETECTION);
        }

        $verdictPriority = ['ALERT' => 3, 'CAUTION' => 2, 'STABLE' => 1, 'INSUFFICIENT_DATA' => 0];
        $worstVerdict    = null;

        foreach ($exerciseRows as $row) {
            /** @var Exercise $exercise */
            $exercise = $row['exercise'];
            $verdict  = $this->computeForExercise($user, $exercise);

            if (
                $worstVerdict === null
                || ($verdictPriority[$verdict->verdict] ?? 0) > ($verdictPriority[$worstVerdict->verdict] ?? 0)
            ) {
                $worstVerdict = $verdict;
            }
        }

        return $worstVerdict ?? AnalyticsVerdict::insufficientData(AnalyticsModule::FATIGUE_DETECTION);
    }
}
