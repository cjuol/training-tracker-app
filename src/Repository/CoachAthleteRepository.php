<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CoachAthlete;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CoachAthlete>
 */
class CoachAthleteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoachAthlete::class);
    }

    /**
     * Returns true when $athlete is in the roster of $coach.
     */
    public function isAthleteOfCoach(User $coach, User $athlete): bool
    {
        $count = (int) $this->createQueryBuilder('ca')
            ->select('COUNT(ca.id)')
            ->andWhere('ca.coach = :coach')
            ->andWhere('ca.athlete = :athlete')
            ->setParameter('coach', $coach)
            ->setParameter('athlete', $athlete)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Returns all athletes assigned to this coach.
     *
     * @return User[]
     */
    public function findAthletesForCoach(User $coach): array
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->join(CoachAthlete::class, 'ca', 'WITH', 'ca.athlete = u AND ca.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
