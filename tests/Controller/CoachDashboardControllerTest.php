<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AssignedMesocycle;
use App\Entity\CoachAthlete;
use App\Entity\Mesocycle;
use App\Entity\User;
use App\Enum\AssignmentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for CoachDashboardController.
 */
class CoachDashboardControllerTest extends WebTestCase
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

    private function linkCoachToAthlete(User $coach, User $athlete): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $ca = new CoachAthlete();
        $ca->setCoach($coach);
        $ca->setAthlete($athlete);
        $em->persist($ca);
        $em->flush();
    }

    private function createActiveMesocycleForAthlete(User $coach, User $athlete): AssignedMesocycle
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $mesocycle = new Mesocycle();
        $mesocycle->setTitle('Meso Dashboard Test');
        $mesocycle->setDescription('');
        $mesocycle->setCoach($coach);
        $em->persist($mesocycle);

        $assignment = new AssignedMesocycle();
        $assignment->setMesocycle($mesocycle);
        $assignment->setAthlete($athlete);
        $assignment->setAssignedBy($coach);
        $assignment->setStartDate(new \DateTimeImmutable('today'));
        $assignment->setStatus(AssignmentStatus::Active);
        $em->persist($assignment);

        $em->flush();

        return $assignment;
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function testAthleteCannotAccessCoachDashboard(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("ath.{$suffix}@coachdash.test", ['ROLE_ATLETA']);

        $client->loginUser($athlete);
        $client->request('GET', '/coach/dashboard');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCoachCanAccessCoachDashboard(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $coach = $this->createUser("coach.{$suffix}@coachdash.test", ['ROLE_ENTRENADOR']);

        $client->loginUser($coach);
        $client->request('GET', '/coach/dashboard');

        $this->assertResponseStatusCodeSame(302);
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        // With role_hierarchy, coach now also passes ROLE_ATLETA, so the dashboard renders
        // the athlete section first (h2 = "Mis Mesociclos Activos") followed by the coach
        // section. Check the body for the coach panel heading instead of the first h2.
        $this->assertSelectorTextContains('body', 'Panel del Entrenador');
    }

    public function testCoachDashboardShowsAssignedAthleteName(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $coach = $this->createUser("coach.{$suffix}@coachdash.test", ['ROLE_ENTRENADOR']);
        $athlete = $this->createUser("ath.{$suffix}@coachdash.test", ['ROLE_ATLETA']);

        // athlete must have a recognizable first+last name
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $athlete->setFirstName('Juan');
        $athlete->setLastName('Pérez');
        $em->flush();

        $this->linkCoachToAthlete($coach, $athlete);

        $client->loginUser($coach);
        $client->request('GET', '/coach/dashboard');

        $this->assertResponseStatusCodeSame(302);
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Juan Pérez');
    }

    // -------------------------------------------------------------------------
    // /dashboard redirect behaviour
    // -------------------------------------------------------------------------

    public function testDashboardRendersUnifiedDashboardForCoach(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $coach = $this->createUser("coach.{$suffix}@dashrd.test", ['ROLE_ENTRENADOR']);

        $client->loginUser($coach);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        // With role_hierarchy, coach also satisfies ROLE_ATLETA, so the athlete section
        // (h2 "Mis Mesociclos Activos") appears before the coach section. Check body.
        $this->assertSelectorTextContains('body', 'Panel del Entrenador');
    }

    public function testDashboardDoesNotRedirectAthlete(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("ath.{$suffix}@dashrd.test", ['ROLE_ATLETA']);

        $client->loginUser($athlete);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        // Athlete dashboard renders "Mi Historial" link and own content
        $this->assertSelectorTextContains('h1', 'Bienvenido');
    }
}
