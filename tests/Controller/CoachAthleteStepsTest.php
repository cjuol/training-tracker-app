<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CoachAthlete;
use App\Entity\DailySteps;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for the coach steps history route in CoachDashboardController.
 */
class CoachAthleteStepsTest extends WebTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createUser(string $email, array $roles, string $password = 'pass123'): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $u = new User();
        $u->setEmail($email);
        $u->setFirstName('Test');
        $u->setLastName('User');
        $u->setRoles($roles);
        $u->setPassword($hasher->hashPassword($u, $password));

        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function linkCoachAthlete(User $coach, User $athlete): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $ca = new CoachAthlete();
        $ca->setCoach($coach);
        $ca->setAthlete($athlete);
        $em->persist($ca);
        $em->flush();
    }

    private function createStepsEntry(User $athlete, int $steps = 8000): DailySteps
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $entry = new DailySteps();
        $entry->setUser($athlete);
        $entry->setDate(new \DateTimeImmutable('today'));
        $entry->setSteps($steps);
        $em->persist($entry);
        $em->flush();

        return $entry;
    }

    // -------------------------------------------------------------------------
    // (1) Coach can view assigned athlete's steps — 200
    // -------------------------------------------------------------------------

    public function testCoachCanViewAthleteSteps(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createUser("coach.{$suffix}@steps.test", ['ROLE_ENTRENADOR']);
        $athlete = $this->createUser("athlete.{$suffix}@steps.test", ['ROLE_ATLETA']);

        $this->linkCoachAthlete($coach, $athlete);
        $this->createStepsEntry($athlete);

        $client->loginUser($coach);
        $client->request('GET', '/coach/athletes/'.$athlete->getId().'/steps');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Pasos de');
    }

    // -------------------------------------------------------------------------
    // (2) Coach cannot view steps of athlete not assigned to them — 403
    // -------------------------------------------------------------------------

    public function testCoachCannotViewNonAssignedAthleteSteps(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createUser("coach.{$suffix}@steps.test", ['ROLE_ENTRENADOR']);
        $unlinkedAthlete = $this->createUser("unlinked.{$suffix}@steps.test", ['ROLE_ATLETA']);

        // No CoachAthlete link created

        $client->loginUser($coach);
        $client->request('GET', '/coach/athletes/'.$unlinkedAthlete->getId().'/steps');

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // (3) Unauthenticated user is redirected to login
    // -------------------------------------------------------------------------

    public function testUnauthenticatedRedirectsToLogin(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $athlete = $this->createUser("athlete.{$suffix}@steps.test", ['ROLE_ATLETA']);

        $client->request('GET', '/coach/athletes/'.$athlete->getId().'/steps');

        $this->assertResponseRedirects('/login');
    }

    // -------------------------------------------------------------------------
    // (4) Athlete cannot access the coach route — 403
    // -------------------------------------------------------------------------

    public function testAthleteCannotAccessCoachRoute(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $athlete = $this->createUser("athlete.{$suffix}@steps.test", ['ROLE_ATLETA']);
        $otherAthlete = $this->createUser("other.{$suffix}@steps.test", ['ROLE_ATLETA']);

        $client->loginUser($athlete);
        $client->request('GET', '/coach/athletes/'.$otherAthlete->getId().'/steps');

        $this->assertResponseStatusCodeSame(403);
    }
}
