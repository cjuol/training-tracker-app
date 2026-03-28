<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FitbitToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FitbitToken>
 */
class FitbitTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FitbitToken::class);
    }

    public function findByUser(User $user): ?FitbitToken
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
