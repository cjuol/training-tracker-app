<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exercise>
 */
class ExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercise::class);
    }

    /**
     * Returns all exercises (created by any coach), optionally filtered by name.
     *
     * @return Exercise[]
     */
    public function findAllWithSearch(?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.name', 'ASC');

        if (null !== $search && '' !== $search) {
            $qb->andWhere('e.name LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns all exercises created by the given user.
     *
     * @return Exercise[]
     */
    public function findByCreator(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
