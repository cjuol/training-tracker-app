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
        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT CAST(sl.logged_at AS DATE) AS date,
                   SUM(sl.weight * sl.reps) AS load
            FROM set_log sl
            JOIN workout_log wl ON sl.workout_log_id = wl.id
            JOIN session_exercise se ON sl.session_exercise_id = se.id
            JOIN exercise e ON se.exercise_id = e.id
            WHERE wl.athlete_id = :userId
              AND e.id = :exerciseId
              AND sl.logged_at >= :from
              AND sl.logged_at <= :to
              AND sl.weight IS NOT NULL
              AND sl.reps IS NOT NULL
            GROUP BY CAST(sl.logged_at AS DATE)
            ORDER BY date ASC
        ';

        $rows = $conn->executeQuery($sql, [
            'userId'     => $user->getId(),
            'exerciseId' => $exercise->getId(),
            'from'       => $from->format('Y-m-d H:i:s'),
            'to'         => $to->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        return array_map(
            fn(array $r) => ['date' => (string) $r['date'], 'load' => (float) $r['load']],
            $rows,
        );
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

        $conn = $this->getEntityManager()->getConnection();
        $sql = '
            SELECT EXTRACT(ISOYEAR FROM sl.logged_at) AS yr,
                   EXTRACT(WEEK FROM sl.logged_at)    AS wk,
                   e.muscle_group                     AS muscle_group,
                   SUM(sl.weight * sl.reps)           AS tonnage
            FROM set_log sl
            JOIN workout_log wl ON sl.workout_log_id = wl.id
            JOIN session_exercise se ON sl.session_exercise_id = se.id
            JOIN exercise e ON se.exercise_id = e.id
            WHERE wl.athlete_id = :userId
              AND sl.logged_at >= :from
              AND sl.weight IS NOT NULL
              AND sl.reps IS NOT NULL
              AND e.muscle_group IS NOT NULL
            GROUP BY yr, wk, e.muscle_group
            ORDER BY yr ASC, wk ASC
        ';

        $rows = $conn->executeQuery($sql, [
            'userId' => $user->getId(),
            'from'   => $from->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        return array_map(
            fn(array $r) => [
                'week'        => $r['yr'] . '-W' . str_pad((string) $r['wk'], 2, '0', STR_PAD_LEFT),
                'muscleGroup' => $r['muscle_group'],
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
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('e AS exercise, COUNT(DISTINCT wl.id) AS sessionCount')
            ->from(Exercise::class, 'e')
            ->join(SessionExercise::class, 'se', 'WITH', 'se.exercise = e')
            ->join(SetLog::class, 'sl', 'WITH', 'sl.sessionExercise = se')
            ->join(WorkoutLog::class, 'wl', 'WITH', 'sl.workoutLog = wl')
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
