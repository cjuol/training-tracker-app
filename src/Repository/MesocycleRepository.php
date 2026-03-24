<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Mesocycle;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mesocycle>
 */
class MesocycleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mesocycle::class);
    }

    /**
     * @return Mesocycle[]
     */
    public function findByCoach(User $coach): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByInviteCode(string $code): ?Mesocycle
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.inviteCode = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
