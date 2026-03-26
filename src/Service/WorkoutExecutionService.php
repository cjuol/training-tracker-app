<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AssignedMesocycle;
use App\Entity\SessionExercise;
use App\Entity\SetLog;
use App\Entity\User;
use App\Entity\WorkoutLog;
use App\Entity\WorkoutSession;
use App\Enum\AssignmentStatus;
use App\Enum\MeasurementType;
use App\Enum\WorkoutStatus;
use App\Repository\SessionExerciseRepository;
use App\Repository\SetLogRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fat service encapsulating all workout execution business logic.
 *
 * Locking contract (mirrors Stimulus workout-controller.js):
 *   - normal_ts / amrap / complex  → sequential lock on currentExerciseId
 *   - superseries                  → currentExerciseId = null while within the group
 */
class WorkoutExecutionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SetLogRepository $setLogRepository,
        private readonly SessionExerciseRepository $sessionExerciseRepository,
    ) {
    }

    /**
     * Creates and persists a new WorkoutLog for the given athlete and session.
     *
     * @throws \DomainException if the assignment is not active or doesn't belong to the athlete
     */
    public function startWorkout(
        User $athlete,
        AssignedMesocycle $assignment,
        WorkoutSession $session,
    ): WorkoutLog {
        if (AssignmentStatus::Active !== $assignment->getStatus()) {
            throw new \DomainException('La asignación no está activa.');
        }

        if ($assignment->getAthlete()->getId() !== $athlete->getId()) {
            throw new \DomainException('La asignación no pertenece a este atleta.');
        }

        $log = new WorkoutLog();
        $log->setAthlete($athlete);
        $log->setWorkoutSession($session);
        $log->setAssignedMesocycle($assignment);

        // Set current exercise to the first by orderIndex
        $firstExercise = $this->getFirstExercise($session);
        if (null !== $firstExercise) {
            // For superseries, start with null (group unlocked); otherwise lock to first
            if ($firstExercise->getSeriesType()->isSuperseriesType()) {
                $log->setCurrentExerciseId(null);
            } else {
                $log->setCurrentExerciseId($firstExercise->getId());
            }
        }

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $log;
    }

    /**
     * Records a set for the given session exercise within a workout log.
     *
     * @param array<string, mixed> $data
     *
     * @throws \DomainException          if the athlete doesn't own the workout log
     * @throws \InvalidArgumentException if required fields for the measurement type are missing
     */
    public function logSet(WorkoutLog $log, SessionExercise $sessionExercise, array $data): SetLog
    {
        $measurementType = $sessionExercise->getExercise()->getMeasurementType();

        $this->validateSetData($measurementType, $data);

        $setNumber = $this->setLogRepository->countForExercise($log, $sessionExercise) + 1;

        $setLog = new SetLog();
        $setLog->setWorkoutLog($log);
        $setLog->setSessionExercise($sessionExercise);
        $setLog->setSetNumber($setNumber);

        $this->fillSetData($setLog, $sessionExercise, $data);

        $this->entityManager->persist($setLog);
        $this->entityManager->flush();

        // Update locking state
        $this->updateCurrentExercise($log, $sessionExercise);

        return $setLog;
    }

    /**
     * Updates the rest time on a previously logged set.
     */
    public function updateRestTime(SetLog $setLog, int $restSeconds): void
    {
        $setLog->setRestTimeSeconds($restSeconds);
        $this->entityManager->flush();
    }

    /**
     * Marks the workout as completed.
     */
    public function completeWorkout(WorkoutLog $log): void
    {
        $log->setStatus(WorkoutStatus::Completed);
        $log->setEndTime(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Determines and updates currentExerciseId after a set has been logged.
     *
     * Locking rules (mirrors workout_controller.js):
     *   - sequential types (normal_ts, amrap, complex):
     *       keep locked until all target sets logged, then advance to next exercise.
     *   - superseries:
     *       all exercises in the same superseriesGroup are available simultaneously
     *       (currentExerciseId = null). Once ALL exercises in the group reach their
     *       target sets, advance past the group.
     */
    private function updateCurrentExercise(WorkoutLog $log, SessionExercise $current): void
    {
        $allExercises = $this->sessionExerciseRepository->findOrderedBySession($log->getWorkoutSession());

        if ($current->getSeriesType()->isSuperseriesType()) {
            $this->updateSuperseriesLock($log, $allExercises, $current);
        } else {
            $loggedForCurrent = $this->setLogRepository->countForExercise($log, $current);
            $this->updateSequentialLock($log, $allExercises, $current, $loggedForCurrent);
        }

        $this->entityManager->flush();
    }

    /**
     * Advances the sequential lock after a set is logged for a non-superseries exercise.
     *
     * Keeps the lock on $current until target sets are reached, then moves to the next exercise.
     *
     * @param SessionExercise[] $allExercises
     */
    private function updateSequentialLock(WorkoutLog $log, array $allExercises, SessionExercise $current, int $loggedForCurrent): void
    {
        if ($loggedForCurrent < $current->getTargetSets()) {
            // Still need more sets — keep lock
            $log->setCurrentExerciseId($current->getId());
        } else {
            // Advance to next exercise after current (by orderIndex)
            $next = $this->findNextExercise($allExercises, $current);
            if (null !== $next) {
                if ($next->getSeriesType()->isSuperseriesType()) {
                    // Next exercise is part of a superseries — unlock the group
                    $log->setCurrentExerciseId(null);
                } else {
                    $log->setCurrentExerciseId($next->getId());
                }
            } else {
                // No next exercise — workout done
                $log->setCurrentExerciseId(null);
            }
        }
    }

    /**
     * Updates the lock state for a superseries group after a set is logged.
     *
     * Keeps currentExerciseId as null (group open) until all exercises in the group
     * reach their target sets, then advances past the group.
     *
     * @param SessionExercise[] $allExercises
     */
    private function updateSuperseriesLock(WorkoutLog $log, array $allExercises, SessionExercise $current): void
    {
        $group = $current->getSuperseriesGroup();
        $groupExercises = array_filter(
            $allExercises,
            static fn (SessionExercise $exercise): bool => $exercise->getSuperseriesGroup() === $group
        );

        $allGroupDone = true;
        foreach ($groupExercises as $groupEx) {
            $logged = $this->setLogRepository->countForExercise($log, $groupEx);
            if ($logged < $groupEx->getTargetSets()) {
                $allGroupDone = false;
                break;
            }
        }

        if (!$allGroupDone) {
            // Still work to do in the group — keep unlocked (null)
            $log->setCurrentExerciseId(null);
        } else {
            // Group done — find the first exercise AFTER the group
            $lastGroupEx = array_reduce(
                $groupExercises,
                static fn (?SessionExercise $carry, SessionExercise $exercise): SessionExercise => (null === $carry || $exercise->getOrderIndex() > $carry->getOrderIndex()) ? $exercise : $carry
            );

            $next = null !== $lastGroupEx
                ? $this->findNextExercise($allExercises, $lastGroupEx)
                : null;

            if (null !== $next) {
                if ($next->getSeriesType()->isSuperseriesType()) {
                    $log->setCurrentExerciseId(null);
                } else {
                    $log->setCurrentExerciseId($next->getId());
                }
            } else {
                $log->setCurrentExerciseId(null);
            }
        }
    }

    /**
     * Populates SetLog fields from $data according to the exercise measurement type,
     * including the optional observation.
     *
     * @param array<string, mixed> $data
     */
    private function fillSetData(SetLog $setLog, SessionExercise $sessionExercise, array $data): void
    {
        $measurementType = $sessionExercise->getExercise()->getMeasurementType();

        match ($measurementType) {
            MeasurementType::RepsWeight => $this->fillRepsWeight($setLog, $data),
            MeasurementType::TimeDistance => $this->fillTimeDistance($setLog, $data),
            MeasurementType::TimeKcal => $this->fillTimeKcal($setLog, $data),
        };

        if (isset($data['observacion']) && '' !== $data['observacion']) {
            $setLog->setObservacion((string) $data['observacion']);
        }
    }

    /**
     * Returns the next SessionExercise after $current by orderIndex, or null if last.
     *
     * @param SessionExercise[] $orderedExercises
     */
    private function findNextExercise(array $orderedExercises, SessionExercise $current): ?SessionExercise
    {
        $found = false;
        foreach ($orderedExercises as $exercise) {
            if ($found) {
                return $exercise;
            }
            if ($exercise->getId() === $current->getId()) {
                $found = true;
            }
        }

        return null;
    }

    /**
     * Returns the first SessionExercise (lowest orderIndex) in the session, or null.
     */
    private function getFirstExercise(WorkoutSession $session): ?SessionExercise
    {
        $exercises = $this->sessionExerciseRepository->findOrderedBySession($session);

        return !empty($exercises) ? $exercises[0] : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException
     */
    private function validateSetData(MeasurementType $type, array $data): void
    {
        match ($type) {
            MeasurementType::RepsWeight => $this->assertRequired($data, 'reps', 'reps_weight requiere "reps" (entero > 0).'),
            MeasurementType::TimeDistance => $this->assertRequired($data, 'timeDuration', 'time_distance requiere "timeDuration" (segundos > 0).'),
            MeasurementType::TimeKcal => $this->assertRequired($data, 'timeDuration', 'time_kcal requiere "timeDuration" (segundos > 0).'),
        };
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException
     */
    private function assertRequired(array $data, string $field, string $message): void
    {
        if (!isset($data[$field]) || (int) $data[$field] <= 0) {
            throw new \InvalidArgumentException($message);
        }
    }

    /** @param array<string, mixed> $data */
    private function fillRepsWeight(SetLog $setLog, array $data): void
    {
        $setLog->setReps((int) $data['reps']);

        if (isset($data['weight']) && '' !== $data['weight']) {
            $setLog->setWeight((float) $data['weight']);
        }

        if (isset($data['rir']) && '' !== $data['rir']) {
            $setLog->setRir((int) $data['rir']);
        }
    }

    /** @param array<string, mixed> $data */
    private function fillTimeDistance(SetLog $setLog, array $data): void
    {
        $setLog->setTimeDuration((int) $data['timeDuration']);

        if (isset($data['distance']) && '' !== $data['distance']) {
            $setLog->setDistance((float) $data['distance']);
        }
    }

    /** @param array<string, mixed> $data */
    private function fillTimeKcal(SetLog $setLog, array $data): void
    {
        $setLog->setTimeDuration((int) $data['timeDuration']);

        if (isset($data['kcal']) && '' !== $data['kcal']) {
            $setLog->setKcal((int) $data['kcal']);
        }
    }
}
