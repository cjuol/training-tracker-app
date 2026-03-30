<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use App\DTO\Analytics\AnalyticsVerdict;
use App\Entity\User;
use App\Repository\SetLogRepository;
use Cjuol\StatGuard\StatsComparator;

class TrainingVolumeAnalyzerService
{
    private const MIN_DATA_POINTS = 5;

    public function __construct(
        private readonly SetLogRepository $setLogRepo,
    ) {}

    public function compute(User $user, int $weeks = 12): AnalyticsVerdict
    {
        $rows = $this->setLogRepo->findWeeklyTonnageByMuscleGroup($user, $weeks);

        if (empty($rows)) {
            return AnalyticsVerdict::insufficientData(AnalyticsModule::TRAINING_VOLUME);
        }

        // Group tonnages by muscleGroup
        $byGroup = [];
        foreach ($rows as $row) {
            $byGroup[$row['muscleGroup']][] = $row['tonnage'];
        }

        $verdictPriority = ['ALERT' => 3, 'CAUTION' => 2, 'STABLE' => 1];
        $comparator      = new StatsComparator();
        $perGroupResults = [];
        $worstPriority   = 0;
        $worstVerdict    = 'STABLE';
        $problematic     = [];

        foreach ($byGroup as $group => $tonnages) {
            if (count($tonnages) < self::MIN_DATA_POINTS) {
                $perGroupResults[$group] = ['verdict' => 'INSUFFICIENT_DATA', 'weeks' => count($tonnages)];
                continue;
            }

            $analysis     = $comparator->analyze($tonnages);
            $trendString  = $analysis['verdict'];

            $groupVerdict = match (true) {
                str_contains($trendString, 'ALERT')   => 'ALERT',
                str_contains($trendString, 'CAUTION') => 'CAUTION',
                default                               => 'STABLE',
            };

            $perGroupResults[$group] = [
                'verdict'      => $groupVerdict,
                'weeks'        => count($tonnages),
                'mean_tonnage' => round(array_sum($tonnages) / count($tonnages), 1),
            ];

            if (($verdictPriority[$groupVerdict] ?? 0) > $worstPriority) {
                $worstPriority = $verdictPriority[$groupVerdict];
                $worstVerdict  = $groupVerdict;
            }

            if (in_array($groupVerdict, ['CAUTION', 'ALERT'], true)) {
                $problematic[] = sprintf('%s (%s)', $group, $groupVerdict);
            }
        }

        // If all groups had insufficient data
        if ($worstVerdict === 'STABLE' && empty(array_filter($perGroupResults, fn($r) => ($r['verdict'] ?? '') !== 'INSUFFICIENT_DATA'))) {
            return AnalyticsVerdict::insufficientData(AnalyticsModule::TRAINING_VOLUME);
        }

        $score = match ($worstVerdict) {
            'STABLE'  => 80.0,
            'CAUTION' => 55.0,
            'ALERT'   => 30.0,
            default   => -1.0,
        };

        $insights = [];

        if (empty($problematic)) {
            $insights[] = 'Volumen de entrenamiento estable en todos los grupos musculares.';
        } else {
            $insights[] = 'Grupos musculares con señal de alerta: ' . implode(', ', $problematic) . '.';
            $insights[] = 'Considera revisar la distribución del volumen semanal.';
        }

        $groupsWithData = array_filter($perGroupResults, fn($r) => $r['verdict'] !== 'INSUFFICIENT_DATA');
        if (count($groupsWithData) > 0) {
            $insights[] = sprintf('%d grupo(s) muscular(es) analizados con datos suficientes.', count($groupsWithData));
        }

        return new AnalyticsVerdict(
            module: AnalyticsModule::TRAINING_VOLUME,
            verdict: $worstVerdict,
            score: $score,
            insights: $insights,
            outliers: [],
            computedAt: new \DateTimeImmutable(),
        );
    }
}
