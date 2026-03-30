<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use App\DTO\Analytics\AnalyticsVerdict;
use App\Entity\AnalyticsSnapshot;
use App\Entity\User;
use App\Repository\AnalyticsSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;

class AnalyticsSnapshotService
{
    private const TTL_HOURS = 6;

    public function __construct(
        private readonly RecoveryIndexService $recoveryIndex,
        private readonly SleepScoringService $sleepScoring,
        private readonly FatigueDetectionService $fatigueDetection,
        private readonly BodyMeasurementAnalyzerService $bodyMeasurement,
        private readonly TrainingVolumeAnalyzerService $trainingVolume,
        private readonly AnalyticsSnapshotRepository $snapshotRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Returns the verdict for a single module, using cache when fresh.
     */
    public function get(User $user, string $module): AnalyticsVerdict
    {
        $snapshot = $this->snapshotRepo->findByUserAndModule($user, $module);

        if ($snapshot !== null && $this->isFresh($snapshot)) {
            return $this->hydrateVerdict($snapshot);
        }

        $verdict = match ($module) {
            AnalyticsModule::RECOVERY_INDEX    => $this->recoveryIndex->compute($user),
            AnalyticsModule::SLEEP_SCORING     => $this->sleepScoring->compute($user),
            AnalyticsModule::FATIGUE_DETECTION => $this->fatigueDetection->computeForUser($user),
            AnalyticsModule::BODY_MEASUREMENT  => $this->bodyMeasurement->compute($user),
            AnalyticsModule::TRAINING_VOLUME   => $this->trainingVolume->compute($user),
            default => throw new \InvalidArgumentException("Unknown analytics module: {$module}"),
        };

        $this->upsertSnapshot($user, $module, $verdict);
        $this->em->flush();

        return $verdict;
    }

    /**
     * Returns verdicts for all 5 modules. Triggers recompute on stale/missing cache.
     *
     * @return array<string, AnalyticsVerdict>
     */
    public function getAll(User $user): array
    {
        $results = [];
        foreach (AnalyticsModule::ALL as $module) {
            $results[$module] = $this->get($user, $module);
        }

        return $results;
    }

    /**
     * Batch-load fresh cached verdicts for multiple users (coach dashboard).
     * Does NOT trigger recomputation — returns only what is already cached.
     *
     * @param User[] $users
     * @return array<int, array<string, AnalyticsVerdict>>  keyed by userId → [module → AnalyticsVerdict]
     */
    public function getCachedForUsers(array $users): array
    {
        if (empty($users)) {
            return [];
        }

        $snapshots = $this->snapshotRepo->findFreshForUsers($users, self::TTL_HOURS);
        $map       = [];

        foreach ($snapshots as $snap) {
            $uid             = $snap->getUser()->getId();
            $map[$uid][$snap->getModule()] = $this->hydrateVerdict($snap);
        }

        return $map;
    }

    /**
     * Invalidates cached snapshots for specific modules, forcing recomputation on next read.
     * No flush performed — caller is responsible for flushing if needed.
     *
     * @param string[] $modules
     */
    public function invalidate(User $user, array $modules): void
    {
        $this->snapshotRepo->deleteByUserAndModules($user, $modules);
    }

    // ------------------------------------------------------------------

    private function isFresh(AnalyticsSnapshot $snap): bool
    {
        $cutoff = new \DateTimeImmutable('-' . self::TTL_HOURS . ' hours');

        return $snap->getComputedAt() >= $cutoff;
    }

    private function hydrateVerdict(AnalyticsSnapshot $snap): AnalyticsVerdict
    {
        $p = $snap->getPayload();

        return new AnalyticsVerdict(
            module: $p['module'],
            verdict: $p['verdict'],
            score: (float) $p['score'],
            insights: $p['insights'],
            outliers: $p['outliers'],
            computedAt: new \DateTimeImmutable($p['computedAt']),
        );
    }

    private function upsertSnapshot(User $user, string $module, AnalyticsVerdict $verdict): void
    {
        $snap = $this->snapshotRepo->findByUserAndModule($user, $module) ?? new AnalyticsSnapshot();

        $today = new \DateTimeImmutable('today');

        $snap->setUser($user);
        $snap->setModule($module);
        $snap->setComputedAt(new \DateTimeImmutable());
        $snap->setPeriodStart($today->modify('-30 days'));
        $snap->setPeriodEnd($today);
        $snap->setPayload($verdict->toArray());

        $this->em->persist($snap);
    }
}
