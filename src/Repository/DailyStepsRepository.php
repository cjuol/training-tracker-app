<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DailySteps;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailySteps>
 */
class DailyStepsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailySteps::class);
    }

    public function findByUserAndDate(User $user, \DateTimeImmutable $date): ?DailySteps
    {
        return $this->createQueryBuilder('ds')
            ->andWhere('ds.user = :user')
            ->andWhere('ds.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return DailySteps[]
     */
    public function findByUserForPeriod(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('ds')
            ->andWhere('ds.user = :user')
            ->andWhere('ds.date >= :from')
            ->andWhere('ds.date <= :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('ds.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
