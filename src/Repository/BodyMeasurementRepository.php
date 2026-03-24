<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BodyMeasurement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BodyMeasurement>
 */
class BodyMeasurementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BodyMeasurement::class);
    }

    /**
     * Returns the last 10 measurements for an athlete, ordered by date descending.
     *
     * @return BodyMeasurement[]
     */
    public function findLast10ByAthlete(User $athlete): array
    {
        return $this->createQueryBuilder('bm')
            ->andWhere('bm.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('bm.measurementDate', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns a paginated list of measurements for an athlete, ordered by date descending.
     *
     * @return BodyMeasurement[]
     */
    public function findByAthletePaginated(User $athlete, int $page, int $pageSize = 10): array
    {
        $offset = ($page - 1) * $pageSize;

        return $this->createQueryBuilder('bm')
            ->andWhere('bm.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('bm.measurementDate', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the total number of measurements for an athlete.
     */
    public function countByAthlete(User $athlete): int
    {
        return (int) $this->createQueryBuilder('bm')
            ->select('COUNT(bm.id)')
            ->andWhere('bm.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
