<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DailyHeartRate;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyHeartRate>
 */
class DailyHeartRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyHeartRate::class);
    }

    public function findByUserAndDate(User $user, \DateTimeInterface $date): ?DailyHeartRate
    {
        return $this->createQueryBuilder('dhr')
            ->andWhere('dhr.user = :user')
            ->andWhere('dhr.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return DailyHeartRate[]
     */
    public function findRecentByUser(User $user, int $limit = 30): array
    {
        return $this->createQueryBuilder('dhr')
            ->andWhere('dhr.user = :user')
            ->setParameter('user', $user)
            ->orderBy('dhr.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
