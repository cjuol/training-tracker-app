<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SessionExercise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SessionExercise>
 */
class SessionExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionExercise::class);
    }

    /**
     * Returns all SessionExercises for the given WorkoutSession, ordered by orderIndex ASC.
     *
     * @return SessionExercise[]
     */
    public function findOrderedBySession(\App\Entity\WorkoutSession $session): array
    {
        return $this->createQueryBuilder('se')
            ->andWhere('se.workoutSession = :session')
            ->setParameter('session', $session)
            ->orderBy('se.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
