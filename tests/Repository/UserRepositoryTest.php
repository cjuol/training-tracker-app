<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\CoachAthlete;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for UserRepository::findAthletesForCoach.
 *
 * Each test wraps DB operations in a transaction that is rolled back in tearDown,
 * ensuring test isolation without needing to reload fixtures.
 */
class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = static::getContainer()->get(UserRepository::class);

        // Begin a transaction to roll back after each test — keeps DB clean
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Roll back all DB changes made during this test
        $this->em->getConnection()->rollBack();
        $this->em->clear();

        parent::tearDown();
    }

    private function createAndPersistUser(string $email, string $firstName, string $lastName, array $roles): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles($roles);
        $user->setPassword('hashed_password_not_relevant_here');

        $this->em->persist($user);

        return $user;
    }

    public function testFindAthletesForCoachReturnsLinkedAthletes(): void
    {
        $suffix = uniqid('', true);
        $coach = $this->createAndPersistUser("coach.{$suffix}@example.com", 'Carlos', 'López', ['ROLE_ENTRENADOR']);
        $athlete1 = $this->createAndPersistUser("athlete1.{$suffix}@example.com", 'Ana', 'García', ['ROLE_ATLETA']);
        $athlete2 = $this->createAndPersistUser("athlete2.{$suffix}@example.com", 'Pedro', 'Ruiz', ['ROLE_ATLETA']);
        $unlinkedAthlete = $this->createAndPersistUser("unlinked.{$suffix}@example.com", 'Marta', 'Soto', ['ROLE_ATLETA']);

        $link1 = new CoachAthlete();
        $link1->setCoach($coach);
        $link1->setAthlete($athlete1);
        $this->em->persist($link1);

        $link2 = new CoachAthlete();
        $link2->setCoach($coach);
        $link2->setAthlete($athlete2);
        $this->em->persist($link2);

        $this->em->flush();

        $athletes = $this->userRepository->findAthletesForCoach($coach);

        $this->assertCount(2, $athletes, 'Coach should have exactly 2 linked athletes.');

        $athleteEmails = array_map(fn (User $u) => $u->getEmail(), $athletes);
        $this->assertContains("athlete1.{$suffix}@example.com", $athleteEmails);
        $this->assertContains("athlete2.{$suffix}@example.com", $athleteEmails);
        $this->assertNotContains("unlinked.{$suffix}@example.com", $athleteEmails);
    }

    public function testFindAthletesForCoachReturnsEmptyArrayWhenNoAthletes(): void
    {
        $suffix = uniqid('', true);
        $coach = $this->createAndPersistUser("lonely.coach.{$suffix}@example.com", 'Solo', 'Coach', ['ROLE_ENTRENADOR']);
        $this->em->flush();

        $athletes = $this->userRepository->findAthletesForCoach($coach);

        $this->assertCount(0, $athletes, 'Coach with no linked athletes should return an empty array.');
    }

    public function testFindAthletesForCoachDoesNotReturnOtherCoachAthletes(): void
    {
        $suffix = uniqid('', true);
        $coach1 = $this->createAndPersistUser("coach1.{$suffix}@example.com", 'Coach', 'One', ['ROLE_ENTRENADOR']);
        $coach2 = $this->createAndPersistUser("coach2.{$suffix}@example.com", 'Coach', 'Two', ['ROLE_ENTRENADOR']);
        $athlete = $this->createAndPersistUser("shared.athlete.{$suffix}@example.com", 'Shared', 'Athlete', ['ROLE_ATLETA']);

        $link = new CoachAthlete();
        $link->setCoach($coach2);
        $link->setAthlete($athlete);
        $this->em->persist($link);
        $this->em->flush();

        $athletesForCoach1 = $this->userRepository->findAthletesForCoach($coach1);

        $this->assertCount(0, $athletesForCoach1, 'Coach 1 should not see athletes linked to Coach 2.');
    }
}
