<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CoachAthlete;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (re-hash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Returns all users that have a valid (non-revoked) Fitbit token.
     *
     * @return User[]
     */
    public function findUsersWithValidFitbitToken(): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.fitbitToken', 'ft')
            ->where('ft.isValid = true')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all athletes linked to the given coach via the CoachAthlete join entity.
     *
     * @return User[]
     */
    public function findAthletesForCoach(User $coach): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin(CoachAthlete::class, 'ca', 'WITH', 'ca.athlete = u AND ca.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
