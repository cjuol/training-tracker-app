<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\BodyMeasurement;
use App\Entity\CoachAthlete;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for coach measurement routes in CoachDashboardController.
 */
class CoachMeasurementsTest extends WebTestCase
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

    private function createMeasurement(User $athlete): BodyMeasurement
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $m = new BodyMeasurement();
        $m->setAthlete($athlete);
        $m->setMeasurementDate(new \DateTimeImmutable('today'));
        $m->setWeightKg(72.00);
        $em->persist($m);
        $em->flush();

        return $m;
    }

    // -------------------------------------------------------------------------
    // (a) GET /coach/athletes/{id}/measurements as coach with assigned athlete → 200
    // -------------------------------------------------------------------------

    public function testCoachCanViewAssignedAthletesMeasurements(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createUser("coach.{$suffix}@meas.test", ['ROLE_ENTRENADOR']);
        $athlete = $this->createUser("athlete.{$suffix}@meas.test", ['ROLE_ATLETA']);

        $this->linkCoachAthlete($coach, $athlete);
        $this->createMeasurement($athlete);

        $client->loginUser($coach);
        $client->request('GET', '/coach/athletes/'.$athlete->getId().'/measurements');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mediciones de');
    }

    // -------------------------------------------------------------------------
    // (b) Same route for non-assigned athlete → 403
    // -------------------------------------------------------------------------

    public function testCoachCannotViewNonAssignedAthletesMeasurements(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createUser("coach.{$suffix}@meas.test", ['ROLE_ENTRENADOR']);
        $unlinkedAthlete = $this->createUser("unlinked.{$suffix}@meas.test", ['ROLE_ATLETA']);

        // No CoachAthlete link created

        $client->loginUser($coach);
        $client->request('GET', '/coach/athletes/'.$unlinkedAthlete->getId().'/measurements');

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // (c) POST to athlete mutation route as coach — coach now inherits ROLE_ATLETA
    // -------------------------------------------------------------------------

    public function testCoachCanCreateOwnMeasurement(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createUser("coach.{$suffix}@meas.test", ['ROLE_ENTRENADOR']);
        $athlete = $this->createUser("athlete.{$suffix}@meas.test", ['ROLE_ATLETA']);

        $this->linkCoachAthlete($coach, $athlete);

        // Coach now inherits ROLE_ATLETA via role_hierarchy, so the guard passes.
        // An empty POST renders the form with validation errors → 200.
        $client->loginUser($coach);
        $client->request('POST', '/profile/measurements/new');

        $this->assertResponseIsSuccessful();
    }
}
