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
 * Functional tests for AssignmentController.
 *
 * Each test creates isolated data with unique e-mail suffixes so tests run
 * in any order without collisions.
 */
class AssignmentControllerTest extends WebTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCoach(string $suffix): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail("coach.{$suffix}@assign.test");
        $user->setFirstName('Coach');
        $user->setLastName('Test');
        $user->setRoles(['ROLE_ENTRENADOR']);
        $user->setPassword($hasher->hashPassword($user, 'password123'));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createAthlete(string $suffix): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail("athlete.{$suffix}@assign.test");
        $user->setFirstName('Atleta');
        $user->setLastName('Test');
        $user->setRoles(['ROLE_ATLETA']);
        $user->setPassword($hasher->hashPassword($user, 'password123'));

        $em->persist($user);
        $em->flush();

        return $user;
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

    private function createMesocycle(User $coach, string $title = 'Test Mesocycle'): Mesocycle
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $m = new Mesocycle();
        $m->setTitle($title);
        $m->setDescription('Test');
        $m->setCoach($coach);
        $em->persist($m);
        $em->flush();

        return $m;
    }

    private function createAssignment(User $coach, User $athlete, Mesocycle $mesocycle): AssignedMesocycle
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $a = new AssignedMesocycle();
        $a->setAthlete($athlete);
        $a->setAssignedBy($coach);
        $a->setMesocycle($mesocycle);
        $a->setStartDate(new \DateTimeImmutable('today'));
        $a->setStatus(AssignmentStatus::Active);
        $em->persist($a);
        $em->flush();

        return $a;
    }

    // -------------------------------------------------------------------------
    // List — access control
    // -------------------------------------------------------------------------

    public function testListRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/assignments');

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function testListRequiresRoleEntrenador(): void
    {
        $client = static::createClient();
        $athlete = $this->createAthlete(uniqid('', true));
        $client->loginUser($athlete);

        $client->request('GET', '/assignments');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testListIsAccessibleByCoach(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));
        $client->loginUser($coach);

        $client->request('GET', '/assignments');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Asignaciones');
    }

    // -------------------------------------------------------------------------
    // Create — coach can assign to own athlete
    // -------------------------------------------------------------------------

    public function testCoachCanCreateAssignmentForOwnAthlete(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $coach = $this->createCoach($suffix);
        $athlete = $this->createAthlete($suffix);
        $this->linkCoachAthlete($coach, $athlete);
        $mesocycle = $this->createMesocycle($coach, 'MC Asignar '.$suffix);
        $client->loginUser($coach);

        $crawler = $client->request('GET', '/assignments/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Asignar')->form([
            'assigned_mesocycle[athlete]' => (string) $athlete->getId(),
            'assigned_mesocycle[mesocycle]' => (string) $mesocycle->getId(),
            'assigned_mesocycle[startDate]' => (new \DateTimeImmutable())->format('Y-m-d'),
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/assignments');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Mesociclo asignado correctamente');
    }

    // -------------------------------------------------------------------------
    // Create — coach cannot assign to athlete not in their roster
    // -------------------------------------------------------------------------

    public function testCoachCannotAssignToAthleteNotInRoster(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $coach = $this->createCoach($suffix);
        $otherAthlete = $this->createAthlete('other.'.$suffix); // NOT linked to coach
        $mesocycle = $this->createMesocycle($coach, 'MC Blocked '.$suffix);
        $client->loginUser($coach);

        // Step 1: GET the form to get a valid CSRF token via the session
        $crawler = $client->request('GET', '/assignments/new');
        $this->assertResponseIsSuccessful();

        // Step 2: Submit with a crafted athlete id (not in the form's query_builder).
        // Symfony's EntityType will reject the value as an invalid choice.
        // We post raw data to simulate the crafted request.
        $csrfToken = $crawler->filter('input[name="assigned_mesocycle[_token]"]')->attr('value');

        $client->request('POST', '/assignments/new', [
            'assigned_mesocycle' => [
                '_token' => $csrfToken,
                'athlete' => (string) $otherAthlete->getId(),
                'mesocycle' => (string) $mesocycle->getId(),
                'startDate' => (new \DateTimeImmutable())->format('Y-m-d'),
            ],
        ]);

        // The form will consider the athlete choice invalid (not in query_builder results)
        // so it should NOT redirect — it either stays on the form (200) or shows an error.
        // Either way, no assignment should have been persisted.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $assignments = $em->getRepository(AssignedMesocycle::class)->findBy([
            'athlete' => $otherAthlete,
        ]);
        $this->assertCount(0, $assignments, 'No assignment should exist for the unlinked athlete.');
    }

    // -------------------------------------------------------------------------
    // Delete — coach can delete own assignment with valid CSRF
    // -------------------------------------------------------------------------

    public function testDeleteOwnAssignmentWithCsrf(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $coach = $this->createCoach($suffix);
        $athlete = $this->createAthlete($suffix);
        $this->linkCoachAthlete($coach, $athlete);
        $mesocycle = $this->createMesocycle($coach, 'MC Delete '.$suffix);
        $assignment = $this->createAssignment($coach, $athlete, $mesocycle);
        $assignId = $assignment->getId();
        $client->loginUser($coach);

        // Fetch the index page to extract the CSRF token from the delete form
        $crawler = $client->request('GET', '/assignments');
        $deleteSelector = 'form[action="/assignments/'.$assignId.'/delete"]';
        $tokenInput = $crawler->filter($deleteSelector.' input[name="_token"]');
        $this->assertGreaterThan(0, $tokenInput->count(), 'CSRF token input not found in delete form.');
        $csrfToken = $tokenInput->attr('value');

        $client->request('POST', '/assignments/'.$assignId.'/delete', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/assignments');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Asignación eliminada correctamente');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $deleted = $em->find(AssignedMesocycle::class, $assignId);
        $this->assertNull($deleted, 'Assignment should have been deleted from the database.');
    }

    // -------------------------------------------------------------------------
    // Delete — cannot delete another coach's assignment (403)
    // -------------------------------------------------------------------------

    public function testCannotDeleteAnotherCoachAssignment(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $owner = $this->createCoach('owner.'.$suffix);
        $otherCoach = $this->createCoach('other.'.$suffix);
        $athlete = $this->createAthlete($suffix);
        $this->linkCoachAthlete($owner, $athlete);
        $mesocycle = $this->createMesocycle($owner, 'MC Protected '.$suffix);
        $assignment = $this->createAssignment($owner, $athlete, $mesocycle);

        $client->loginUser($otherCoach);
        $client->request('POST', '/assignments/'.$assignment->getId().'/delete', [
            '_token' => 'irrelevant-invalid-token',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
