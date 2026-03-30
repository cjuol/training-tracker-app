<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\AssignedMesocycleRepository;
use App\Repository\CoachAthleteRepository;
use App\Repository\WorkoutLogRepository;
use App\Service\Analytics\AnalyticsSnapshotService;

/**
 * Aggregates per-athlete summary data for the coach dashboard.
 *
 * One service call fetches all athletes, their active mesocycle, and their
 * last workout/completed-session counts in a single logical pass, keeping the
 * controller thin.
 */
class CoachDashboardService
{
    public function __construct(
        private readonly CoachAthleteRepository $coachAthleteRepository,
        private readonly AssignedMesocycleRepository $assignedMesocycleRepository,
        private readonly WorkoutLogRepository $workoutLogRepository,
        private readonly AnalyticsSnapshotService $analyticsSnapshotService,
    ) {
    }

    /**
     * Returns one summary array per athlete assigned to this coach.
     *
     * Shape:
     * [
     *   'athlete'                        => User,
     *   'activeMesocycle'                => string|null,   // title or null
     *   'lastWorkoutDate'                => \DateTimeImmutable|null,
     *   'completedSessionsThisMesocycle' => int,
     *   'verdicts'                       => array<string, AnalyticsVerdict>,  // cached only, may be empty
     * ]
     *
     * @return array<int, array{
     *     athlete: User,
     *     activeMesocycle: string|null,
     *     lastWorkoutDate: \DateTimeImmutable|null,
     *     completedSessionsThisMesocycle: int,
     *     verdicts: array
     * }>
     */
    public function getAthleteSummaries(User $coach): array
    {
        $athletes = $this->coachAthleteRepository->findAthletesForCoach($coach);
        $summaries = [];

        if (empty($athletes)) {
            return [];
        }

        // Batch queries — one per data type for all athletes instead of N×3
        $currentAssignmentsMap = $this->assignedMesocycleRepository->findActiveByAthletesGrouped($athletes);
        $lastDateMap = $this->workoutLogRepository->findLastPerAthletes($athletes);

        // Batch-load cached analytics verdicts (read-only, no recompute)
        $verdictsMap = $this->analyticsSnapshotService->getCachedForUsers($athletes);

        foreach ($athletes as $athlete) {
            $athleteId = $athlete->getId();

            // Active mesocycle title
            $currentAssignments = $currentAssignmentsMap[$athleteId] ?? [];
            // Take the most recently started active assignment (ordered by startDate DESC)
            $currentAssignment = $currentAssignments[0] ?? null;
            $activeMesocycleTitle = $currentAssignment?->getMesocycle()->getTitle();

            // Last workout date (any status, most recent)
            $lastDateRaw = $lastDateMap[$athleteId] ?? null;
            $lastWorkoutDate = null !== $lastDateRaw
                ? new \DateTimeImmutable($lastDateRaw)
                : null;

            // Completed sessions in current mesocycle
            $completedCount = 0;
            if (null !== $currentAssignment) {
                $completedLogs = $this->workoutLogRepository->findCompletedByAthleteAndAssignment(
                    $athlete,
                    (int) $currentAssignment->getId()
                );
                $completedCount = count($completedLogs);
            }

            $summaries[] = [
                'athlete' => $athlete,
                'activeMesocycle' => $activeMesocycleTitle,
                'lastWorkoutDate' => $lastWorkoutDate,
                'completedSessionsThisMesocycle' => $completedCount,
                'verdicts' => $verdictsMap[$athleteId] ?? [],
            ];
        }

        return $summaries;
    }
}
