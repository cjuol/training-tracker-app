<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AssignedMesocycle;
use App\Entity\Mesocycle;
use App\Entity\User;
use App\Enum\AssignmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssignedMesocycle>
 */
class AssignedMesocycleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssignedMesocycle::class);
    }

    /**
     * All assignments created by a coach, ordered by athlete name then start date.
     *
     * @return AssignedMesocycle[]
     */
    public function findByCoach(User $coach): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.athlete', 'u')
            ->andWhere('a.assignedBy = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->addOrderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Active assignments for a given athlete.
     *
     * @return AssignedMesocycle[]
     */
    public function findActiveByAthlete(User $athlete): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.athlete = :athlete')
            ->andWhere('a.status = :status')
            ->setParameter('athlete', $athlete)
            ->setParameter('status', AssignmentStatus::Active)
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AssignedMesocycle[]
     */
    public function findActiveByMesocycle(Mesocycle $mesocycle): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.mesocycle = :mesocycle')
            ->andWhere('a.status = :status')
            ->setParameter('mesocycle', $mesocycle)
            ->setParameter('status', \App\Enum\AssignmentStatus::Active)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AssignedMesocycle|null
     */
    public function findActiveByAthleteAndMesocycle(User $athlete, Mesocycle $mesocycle): ?AssignedMesocycle
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.athlete = :athlete')
            ->andWhere('a.mesocycle = :mesocycle')
            ->andWhere('a.status = :status')
            ->setParameter('athlete', $athlete)
            ->setParameter('mesocycle', $mesocycle)
            ->setParameter('status', \App\Enum\AssignmentStatus::Active)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return AssignedMesocycle[]
     */
    public function findByMesocycle(Mesocycle $mesocycle): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.mesocycle = :mesocycle')
            ->setParameter('mesocycle', $mesocycle)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AssignedMesocycle[]
     */
    public function findByAthlete(User $athlete): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Fetch a single AssignedMesocycle with all nested associations eagerly loaded
     * to prevent N+1 in the athlete detail view.
     * Returns null if not found.
     */
    public function findForAthleteDetail(int $id): ?AssignedMesocycle
    {
        return $this->createQueryBuilder('a')
            ->select('a', 'm', 'coach', 'ws', 'se', 'ex')
            ->join('a.mesocycle', 'm')
            ->join('m.coach', 'coach')
            ->leftJoin('m.workoutSessions', 'ws')
            ->leftJoin('ws.sessionExercises', 'se')
            ->leftJoin('se.exercise', 'ex')
            ->andWhere('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Active assignments for multiple athletes, grouped by athlete ID.
     * Issues a single query for all athletes instead of one per athlete.
     *
     * @param User[] $athletes
     * @return array<int, AssignedMesocycle[]> keyed by athlete ID
     */
    public function findActiveByAthletesGrouped(array $athletes): array
    {
        if (empty($athletes)) {
            return [];
        }

        $assignments = $this->createQueryBuilder('a')
            ->andWhere('a.athlete IN (:athletes)')
            ->andWhere('a.status = :status')
            ->setParameter('athletes', $athletes)
            ->setParameter('status', AssignmentStatus::Active)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($assignments as $assignment) {
            $id = $assignment->getAthlete()->getId();
            $map[$id][] = $assignment;
        }

        return $map;
    }

    /**
     * Athletes of a coach and their current active assignment (if any).
     * Returns rows: ['athlete' => User, 'assignment' => AssignedMesocycle|null].
     *
     * Single query via LEFT JOIN — no per-athlete sub-queries.
     *
     * @return array<int, array{athlete: User, assignment: AssignedMesocycle|null}>
     */
    public function findAthletesWithActiveAssignmentForCoach(User $coach): array
    {
        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('u', 'a')
            ->from(User::class, 'u')
            ->join(\App\Entity\CoachAthlete::class, 'ca', 'WITH', 'ca.athlete = u AND ca.coach = :coach')
            ->leftJoin(
                AssignedMesocycle::class,
                'a',
                'WITH',
                'a.athlete = u AND a.status = :status'
            )
            ->setParameter('coach', $coach)
            ->setParameter('status', AssignmentStatus::Active)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->addOrderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();

        // Deduplicate: keep only the most-recent active assignment per athlete.
        $seen = [];
        $result = [];
        foreach ($rows as $entity) {
            if ($entity instanceof User) {
                $id = $entity->getId();
                if (!isset($seen[$id])) {
                    $seen[$id] = true;
                    $result[] = ['athlete' => $entity, 'assignment' => null];
                }
            } elseif ($entity instanceof AssignedMesocycle) {
                // Attach to the last athlete entry that has no assignment yet
                foreach (array_reverse(array_keys($result)) as $idx) {
                    if ($result[$idx]['athlete']->getId() === $entity->getAthlete()->getId()
                        && null === $result[$idx]['assignment']
                    ) {
                        $result[$idx]['assignment'] = $entity;
                        break;
                    }
                }
            }
        }

        return $result;
    }
}
