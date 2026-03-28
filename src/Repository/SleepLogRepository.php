<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SleepLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SleepLog>
 */
class SleepLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SleepLog::class);
    }

    public function findByUserAndDate(User $user, \DateTimeInterface $date): ?SleepLog
    {
        return $this->createQueryBuilder('sl')
            ->andWhere('sl.user = :user')
            ->andWhere('sl.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return SleepLog[]
     */
    public function findRecentByUser(User $user, int $limit = 30): array
    {
        return $this->createQueryBuilder('sl')
            ->andWhere('sl.user = :user')
            ->setParameter('user', $user)
            ->orderBy('sl.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
