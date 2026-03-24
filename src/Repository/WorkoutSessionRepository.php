<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Mesocycle;
use App\Entity\WorkoutSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkoutSession>
 */
class WorkoutSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkoutSession::class);
    }

    /**
     * Returns all WorkoutSessions for a mesocycle ordered by orderIndex ascending.
     * Avoids a PHP-side usort() on the full collection.
     *
     * @return WorkoutSession[]
     */
    public function findByMesocycleOrdered(Mesocycle $mesocycle): array
    {
        return $this->createQueryBuilder('ws')
            ->andWhere('ws.mesocycle = :mesocycle')
            ->setParameter('mesocycle', $mesocycle)
            ->orderBy('ws.orderIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
