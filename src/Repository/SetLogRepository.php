<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\SessionExercise;
use App\Entity\SetLog;
use App\Entity\User;
use App\Entity\WorkoutLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SetLog>
 */
class SetLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SetLog::class);
    }

    /**
     * Count how many sets have been logged for a given exercise within a workout.
     */
    public function countForExercise(WorkoutLog $workoutLog, SessionExercise $sessionExercise): int
    {
        return (int) $this->createQueryBuilder('sl')
            ->select('COUNT(sl.id)')
            ->andWhere('sl.workoutLog = :log')
            ->andWhere('sl.sessionExercise = :se')
            ->setParameter('log', $workoutLog)
            ->setParameter('se', $sessionExercise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns a map of sessionExercise ID → logged set count for a given workout log.
     *
     * @return array<int, int>
     */
    public function countByWorkoutLogGrouped(WorkoutLog $workoutLog): array
    {
        $rows = $this->createQueryBuilder('sl')
            ->select('IDENTITY(sl.sessionExercise) AS seId, COUNT(sl.id) AS cnt')
            ->andWhere('sl.workoutLog = :log')
            ->setParameter('log', $workoutLog)
            ->groupBy('sl.sessionExercise')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['seId']] = (int) $row['cnt'];
        }

        return $map;
    }

    /**
     * @return SetLog[]
     */
    public function findForExercise(WorkoutLog $workoutLog, SessionExercise $sessionExercise): array
    {
        return $this->createQueryBuilder('sl')
            ->andWhere('sl.workoutLog = :log')
            ->andWhere('sl.sessionExercise = :se')
            ->setParameter('log', $workoutLog)
            ->setParameter('se', $sessionExercise)
            ->orderBy('sl.setNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns daily effective load (weight × reps) for a given user + exercise over a date range.
     * Rows with null weight or reps are excluded.
     * Results are grouped by calendar date and ordered ascending.
     *
     * @return array<int, array{date: string, load: float}>
     */
    public function findEffectiveLoadByExercise(
        User $user,
        Exercise $exercise,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        return $this->createQueryBuilder('sl')
            ->select('DATE(sl.loggedAt) AS date, SUM(sl.weight * sl.reps) AS load')
            ->join('sl.workoutLog', 'wl')
            ->join('sl.sessionExercise', 'se')
            ->join('se.exercise', 'e')
            ->andWhere('wl.athlete = :user')
            ->andWhere('e = :exercise')
            ->andWhere('sl.loggedAt >= :from')
            ->andWhere('sl.loggedAt <= :to')
            ->andWhere('sl.weight IS NOT NULL')
            ->andWhere('sl.reps IS NOT NULL')
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('exercise', $exercise)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns weekly tonnage (sum of weight × reps) grouped by muscle group and ISO year-week.
     * Exercises without a muscleGroup are excluded.
     *
     * @return array<int, array{week: string, muscleGroup: string, tonnage: float}>
     */
    public function findWeeklyTonnageByMuscleGroup(User $user, int $weeks = 12): array
    {
        $from = new \DateTimeImmutable('-' . $weeks . ' weeks');

        $rows = $this->createQueryBuilder('sl')
            ->select(
                'YEAR(sl.loggedAt) AS yr',
                'WEEK(sl.loggedAt) AS wk',
                'e.muscleGroup AS muscleGroup',
                'SUM(sl.weight * sl.reps) AS tonnage',
            )
            ->join('sl.workoutLog', 'wl')
            ->join('sl.sessionExercise', 'se')
            ->join('se.exercise', 'e')
            ->andWhere('wl.athlete = :user')
            ->andWhere('sl.loggedAt >= :from')
            ->andWhere('sl.weight IS NOT NULL')
            ->andWhere('sl.reps IS NOT NULL')
            ->andWhere('e.muscleGroup IS NOT NULL')
            ->groupBy('yr, wk, e.muscleGroup')
            ->orderBy('yr', 'ASC')
            ->addOrderBy('wk', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            fn(array $r) => [
                'week'        => $r['yr'] . '-W' . str_pad((string) $r['wk'], 2, '0', STR_PAD_LEFT),
                'muscleGroup' => $r['muscleGroup'],
                'tonnage'     => (float) $r['tonnage'],
            ],
            $rows,
        );
    }

    /**
     * Returns a distinct list of exercises that the given user has logged sets for,
     * along with a count of sessions to allow filtering by minimum sessions.
     *
     * @return array<int, array{exercise: Exercise, sessionCount: int}>
     */
    public function findExercisesLoggedByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('sl')
            ->select('e AS exercise, COUNT(DISTINCT wl.id) AS sessionCount')
            ->join('sl.workoutLog', 'wl')
            ->join('sl.sessionExercise', 'se')
            ->join('se.exercise', 'e')
            ->andWhere('wl.athlete = :user')
            ->andWhere('sl.weight IS NOT NULL')
            ->andWhere('sl.reps IS NOT NULL')
            ->groupBy('e.id')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Returns the total tonnage (sum of weight × reps) for a user within a date range.
     * Uses sl.loggedAt for date filtering. Sets with null weight or null reps are excluded.
     * Returns 0.0 if no matching sets are found.
     */
    public function findTonnageForPeriod(
        User $user,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): float {
        $result = $this->createQueryBuilder('sl')
            ->select('SUM(sl.weight * sl.reps) AS tonnage')
            ->join('sl.workoutLog', 'wl')
            ->andWhere('wl.athlete = :user')
            ->andWhere('sl.loggedAt >= :from')
            ->andWhere('sl.loggedAt <= :to')
            ->andWhere('sl.weight IS NOT NULL')
            ->andWhere('sl.reps IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    /**
     * Returns an array of personal records (max weight per exercise) for a given user.
     * One entry per (exercise, session) — the caller may further reduce to all-time max per exercise.
     * Results are ordered by exercise name ascending.
     *
     * @return array<int, array{exercise_name: string, max_weight: float, date: \DateTimeImmutable}>
     */
    public function findPersonalRecordsByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('sl')
            ->select('e.name AS exercise_name, MAX(sl.weight) AS max_weight, wl.startTime AS date')
            ->join('sl.workoutLog', 'wl')
            ->join('sl.sessionExercise', 'se')
            ->join('se.exercise', 'e')
            ->andWhere('wl.athlete = :user')
            ->andWhere('sl.weight IS NOT NULL')
            ->groupBy('e.id, e.name, wl.startTime')
            ->orderBy('e.name', 'ASC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            fn(array $r) => [
                'exercise_name' => (string) $r['exercise_name'],
                'max_weight'    => (float) $r['max_weight'],
                'date'          => $r['date'],
            ],
            $rows,
        );
    }
}
