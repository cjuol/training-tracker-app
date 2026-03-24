<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SessionExercise;
use App\Entity\SetLog;
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
}
