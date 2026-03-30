<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AnalyticsSnapshot;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalyticsSnapshot>
 */
class AnalyticsSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsSnapshot::class);
    }

    public function findByUserAndModule(User $user, string $module): ?AnalyticsSnapshot
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->andWhere('s.module = :module')
            ->setParameter('user', $user)
            ->setParameter('module', $module)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Bulk-delete snapshots for a user and a list of modules.
     * Uses DQL DELETE — safe inside Doctrine postPersist listeners.
     */
    public function deleteByUserAndModules(User $user, array $modules): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->andWhere('s.user = :user')
            ->andWhere('s.module IN (:modules)')
            ->setParameter('user', $user)
            ->setParameter('modules', $modules)
            ->getQuery()
            ->execute();
    }

    /**
     * Batch-load fresh snapshots for multiple users (for coach dashboard).
     * Only returns snapshots computed within the TTL window.
     *
     * @param User[] $users
     * @return AnalyticsSnapshot[]
     */
    public function findFreshForUsers(array $users, int $ttlHours): array
    {
        $cutoff = new \DateTimeImmutable('-' . $ttlHours . ' hours');

        return $this->createQueryBuilder('s')
            ->andWhere('s.user IN (:users)')
            ->andWhere('s.computedAt >= :cutoff')
            ->setParameter('users', $users)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }
}
