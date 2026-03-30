<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorkoutLog;
use App\Entity\WorkoutSession;
use App\Enum\WorkoutStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkoutLog>
 */
class WorkoutLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutLog::class);
    }

    /**
     * Most recent log for an athlete (single query, avoids full collection load).
     *
     * @return WorkoutLog|null
     */
    public function findLastByAthlete(User $athlete): ?WorkoutLog
    {
        return $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('wl.startTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * All logs for an athlete, ordered most recent first.
     *
     * @return WorkoutLog[]
     */
    public function findByAthlete(User $athlete): array
    {
        return $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('wl.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Only completed logs for an athlete, ordered most recent first.
     *
     * @return WorkoutLog[]
     */
    public function findCompletedByAthlete(User $athlete): array
    {
        return $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :athlete')
            ->andWhere('wl.status = :status')
            ->setParameter('athlete', $athlete)
            ->setParameter('status', WorkoutStatus::Completed)
            ->orderBy('wl.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * All logs for a specific athlete + session combination.
     *
     * @return WorkoutLog[]
     */
    public function findByAthleteAndSession(User $athlete, WorkoutSession $session): array
    {
        return $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :athlete')
            ->andWhere('wl.workoutSession = :session')
            ->setParameter('athlete', $athlete)
            ->setParameter('session', $session)
            ->orderBy('wl.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paginated logs for an athlete (manual LIMIT/OFFSET, no bundle needed).
     *
     * @return WorkoutLog[]
     */
    public function findPaginatedByAthlete(User $athlete, int $page, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('wl.startTime', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Total number of logs for an athlete.
     */
    public function countByAthlete(User $athlete): int
    {
        return (int) $this->createQueryBuilder('wl')
            ->select('COUNT(wl.id)')
            ->andWhere('wl.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return WorkoutLog[]
     */
    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :user')
            ->setParameter('user', $user)
            ->orderBy('wl.startTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $assignmentIds
     *
     * @return WorkoutLog[]
     */
    public function findByAthleteAndMesocycleAssignments(User $user, array $assignmentIds): array
    {
        if (empty($assignmentIds)) {
            return [];
        }
        return $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :user')
            ->andWhere('wl.assignedMesocycle IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $assignmentIds)
            ->orderBy('wl.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all in-progress WorkoutLogs for an athlete, keyed by workoutSession ID.
     *
     * @return array<int, WorkoutLog>
     */
    public function findInProgressByAthleteIndexedBySession(User $athlete): array
    {
        $logs = $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :athlete')
            ->andWhere('wl.status = :status')
            ->setParameter('athlete', $athlete)
            ->setParameter('status', WorkoutStatus::InProgress)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($logs as $log) {
            $map[$log->getWorkoutSession()->getId()] = $log;
        }

        return $map;
    }

    /**
     * Returns the last workout date per athlete, keyed by athlete ID.
     * Issues a single query for all athletes instead of one per athlete.
     *
     * @param User[] $athletes
     * @return array<int, string> athleteId => lastDate (string from DB)
     */
    public function findLastPerAthletes(array $athletes): array
    {
        if (empty($athletes)) {
            return [];
        }

        $rows = $this->createQueryBuilder('wl')
            ->select('IDENTITY(wl.athlete) AS athleteId, MAX(wl.startTime) AS lastDate')
            ->andWhere('wl.athlete IN (:athletes)')
            ->setParameter('athletes', $athletes)
            ->groupBy('wl.athlete')
            ->getQuery()
            ->getArrayResult();

        if (empty($rows)) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['athleteId']] = $row['lastDate'];
        }

        return $map;
    }

    /**
     * Returns all in-progress WorkoutLogs for an athlete scoped to one assignment, keyed by workoutSession ID.
     *
     * @return array<int, WorkoutLog>
     */
    public function findInProgressByAthleteAndAssignmentIndexedBySession(User $athlete, int $assignedMesocycleId): array
    {
        $logs = $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :athlete')
            ->andWhere('wl.assignedMesocycle = :assignmentId')
            ->andWhere('wl.status = :status')
            ->setParameter('athlete', $athlete)
            ->setParameter('assignmentId', $assignedMesocycleId)
            ->setParameter('status', WorkoutStatus::InProgress)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($logs as $log) {
            $map[$log->getWorkoutSession()->getId()] = $log;
        }

        return $map;
    }

    /**
     * Completed logs for an athlete within a specific assigned mesocycle.
     *
     * @return WorkoutLog[]
     */
    public function findCompletedByAthleteAndAssignment(User $athlete, int $assignedMesocycleId): array
    {
        return $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :athlete')
            ->andWhere('wl.assignedMesocycle = :assignmentId')
            ->andWhere('wl.status = :status')
            ->setParameter('athlete', $athlete)
            ->setParameter('assignmentId', $assignedMesocycleId)
            ->setParameter('status', WorkoutStatus::Completed)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns completed WorkoutLogs for an athlete with startTime in the given date range.
     * Ordered by startTime ascending.
     *
     * @return WorkoutLog[]
     */
    public function findCompletedByAthleteInDateRange(
        User $athlete,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('wl')
            ->andWhere('wl.athlete = :athlete')
            ->andWhere('wl.startTime >= :from')
            ->andWhere('wl.startTime <= :to')
            ->andWhere('wl.status = :status')
            ->setParameter('athlete', $athlete)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('status', WorkoutStatus::Completed)
            ->orderBy('wl.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
