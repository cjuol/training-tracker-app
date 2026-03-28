<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FitbitActivity;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FitbitActivity>
 */
class FitbitActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FitbitActivity::class);
    }

    public function findByUserAndFitbitLogId(User $user, string $fitbitLogId): ?FitbitActivity
    {
        return $this->createQueryBuilder('fa')
            ->andWhere('fa.user = :user')
            ->andWhere('fa.fitbitLogId = :fitbitLogId')
            ->setParameter('user', $user)
            ->setParameter('fitbitLogId', $fitbitLogId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return FitbitActivity[]
     */
    public function findByUserAndDate(User $user, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('fa')
            ->andWhere('fa.user = :user')
            ->andWhere('fa.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->orderBy('fa.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
