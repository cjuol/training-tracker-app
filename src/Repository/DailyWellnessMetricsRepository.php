<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DailyWellnessMetrics;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyWellnessMetrics>
 */
class DailyWellnessMetricsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyWellnessMetrics::class);
    }

    /**
     * Returns up to 14 DailyWellnessMetrics records for the given user, ordered by date DESC.
     *
     * @return DailyWellnessMetrics[]
     */
    public function findLast14ByUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.date', 'DESC')
            ->setMaxResults(14)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the most recent DailyWellnessMetrics for the user, or null if none exist.
     */
    public function findLatestByUser(User $user): ?DailyWellnessMetrics
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('m.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Finds the wellness record for a specific user and date (used for upsert logic).
     */
    public function findByUserAndDate(User $user, \DateTimeImmutable $date): ?DailyWellnessMetrics
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->andWhere('m.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
