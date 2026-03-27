<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AssignedMesocycle;
use App\Entity\CoachAthlete;
use App\Entity\Exercise;
use App\Entity\Mesocycle;
use App\Entity\SessionExercise;
use App\Entity\User;
use App\Entity\WorkoutSession;
use App\Enum\AssignmentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for AthleteMesocycleController and the invite-code flow
 * (including the regenerateCode action on MesocycleController).
 *
 * Each test creates isolated users/mesocycles with unique e-mail suffixes
 * so tests run in any order without collisions.
 */
class AthleteMesocycleControllerTest extends WebTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCoach(string $suffix): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail("coach.{$suffix}@invite.test");
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
        $user->setEmail("athlete.{$suffix}@invite.test");
        $user->setFirstName('Atleta');
        $user->setLastName('Test');
        $user->setRoles(['ROLE_ATLETA']);
        $user->setPassword($hasher->hashPassword($user, 'password123'));

        $em->persist($user);
        $em->flush();

        return $user;
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

    private function linkCoachAthlete(User $coach, User $athlete): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $ca = new CoachAthlete();
        $ca->setCoach($coach);
        $ca->setAthlete($athlete);
        $em->persist($ca);
        $em->flush();
    }

    private function createActiveAssignment(User $coach, User $athlete, Mesocycle $mesocycle): AssignedMesocycle
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

    /**
     * Fetches the CSRF token for the join form by loading the index page
     * while logged in as $athlete.
     */
    private function getJoinCsrfToken(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, User $athlete): string
    {
        $client->loginUser($athlete);
        $crawler = $client->request('GET', '/my-mesocycles');
        $this->assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->assertNotEmpty($token, 'CSRF token not found in join form.');

        return $token;
    }

    // -------------------------------------------------------------------------
    // 1. testJoinWithValidCode
    // -------------------------------------------------------------------------

    public function testJoinWithValidCode(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $athlete = $this->createAthlete($suffix);
        $mesocycle = $this->createMesocycle($coach, 'MC Valid '.$suffix);
        $inviteCode = $mesocycle->getInviteCode();

        $csrfToken = $this->getJoinCsrfToken($client, $athlete);

        $client->request('POST', '/my-mesocycles/join', [
            '_token' => $csrfToken,
            'invite_code' => $inviteCode,
            'start_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ]);

        $this->assertResponseRedirects('/my-mesocycles');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Verify AssignedMesocycle was created in the DB
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $assignments = $em->getRepository(AssignedMesocycle::class)->findBy([
            'athlete' => $athlete,
            'mesocycle' => $mesocycle,
        ]);
        $this->assertCount(1, $assignments, 'AssignedMesocycle should have been created.');
        $this->assertSame(AssignmentStatus::Active, $assignments[0]->getStatus());

        // Verify CoachAthlete was created
        $coachAthletes = $em->getRepository(CoachAthlete::class)->findBy([
            'coach' => $coach,
            'athlete' => $athlete,
        ]);
        $this->assertCount(1, $coachAthletes, 'CoachAthlete record should have been created.');
    }

    // -------------------------------------------------------------------------
    // 2. testJoinWithInvalidCode
    // -------------------------------------------------------------------------

    public function testJoinWithInvalidCode(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $athlete = $this->createAthlete($suffix);
        $csrfToken = $this->getJoinCsrfToken($client, $athlete);

        $client->request('POST', '/my-mesocycles/join', [
            '_token' => $csrfToken,
            'invite_code' => 'nonexistentXX',
        ]);

        $this->assertResponseRedirects('/my-mesocycles');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'inválido');
    }

    // -------------------------------------------------------------------------
    // 3. testJoinDuplicateActive
    // -------------------------------------------------------------------------

    public function testJoinDuplicateActive(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $athlete = $this->createAthlete($suffix);
        $mesocycle = $this->createMesocycle($coach, 'MC Dup '.$suffix);
        $this->linkCoachAthlete($coach, $athlete);
        $this->createActiveAssignment($coach, $athlete, $mesocycle);

        $csrfToken = $this->getJoinCsrfToken($client, $athlete);

        $client->request('POST', '/my-mesocycles/join', [
            '_token' => $csrfToken,
            'invite_code' => $mesocycle->getInviteCode(),
            'start_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ]);

        $this->assertResponseRedirects('/my-mesocycles');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        // The flash message should mention "asignado" (already assigned)
        $this->assertSelectorTextContains('body', 'asignado');

        // Only one assignment should exist
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $assignments = $em->getRepository(AssignedMesocycle::class)->findBy([
            'athlete' => $athlete,
            'mesocycle' => $mesocycle,
        ]);
        $this->assertCount(1, $assignments, 'Should not have created a duplicate assignment.');
    }

    // -------------------------------------------------------------------------
    // 4. testJoinCreatesCoachAthleteRelationship
    // -------------------------------------------------------------------------

    public function testJoinCreatesCoachAthleteRelationship(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $athlete = $this->createAthlete($suffix);
        $mesocycle = $this->createMesocycle($coach, 'MC NewLink '.$suffix);

        // Ensure no prior CoachAthlete link
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $existing = $em->getRepository(CoachAthlete::class)->findBy([
            'coach' => $coach,
            'athlete' => $athlete,
        ]);
        $this->assertCount(0, $existing, 'Pre-condition: CoachAthlete should not exist yet.');

        $csrfToken = $this->getJoinCsrfToken($client, $athlete);

        $client->request('POST', '/my-mesocycles/join', [
            '_token' => $csrfToken,
            'invite_code' => $mesocycle->getInviteCode(),
            'start_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ]);

        $this->assertResponseRedirects('/my-mesocycles');

        $em->clear();
        $created = $em->getRepository(CoachAthlete::class)->findBy([
            'coach' => $coach,
            'athlete' => $athlete,
        ]);
        $this->assertCount(1, $created, 'CoachAthlete record should have been auto-created on join.');
    }

    // -------------------------------------------------------------------------
    // 5. testJoinDoesNotDuplicateCoachAthlete
    // -------------------------------------------------------------------------

    public function testJoinDoesNotDuplicateCoachAthlete(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $athlete = $this->createAthlete($suffix);
        $mesocycle = $this->createMesocycle($coach, 'MC NoDup '.$suffix);
        $this->linkCoachAthlete($coach, $athlete);

        $csrfToken = $this->getJoinCsrfToken($client, $athlete);

        $client->request('POST', '/my-mesocycles/join', [
            '_token' => $csrfToken,
            'invite_code' => $mesocycle->getInviteCode(),
            'start_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ]);

        $this->assertResponseRedirects('/my-mesocycles');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $links = $em->getRepository(CoachAthlete::class)->findBy([
            'coach' => $coach,
            'athlete' => $athlete,
        ]);
        $this->assertCount(1, $links, 'Should not have created a duplicate CoachAthlete record.');
    }

    // -------------------------------------------------------------------------
    // 6. testJoinRequiresAuthentication
    // -------------------------------------------------------------------------

    public function testJoinRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/my-mesocycles/join', [
            '_token' => 'any-token',
            'invite_code' => 'someCode',
        ]);

        // Unauthenticated → redirect to login
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    // -------------------------------------------------------------------------
    // 7. testCoachCannotAccessMyMesocycles
    // -------------------------------------------------------------------------

    public function testCoachCannotAccessMyMesocycles(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $client->loginUser($coach);

        $client->request('GET', '/my-mesocycles');

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // 8. testRegenerateCodeCancelsActiveAssignments
    // -------------------------------------------------------------------------

    public function testRegenerateCodeCancelsActiveAssignments(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $athlete1 = $this->createAthlete('a1.'.$suffix);
        $athlete2 = $this->createAthlete('a2.'.$suffix);
        $mesocycle = $this->createMesocycle($coach, 'MC Regen '.$suffix);
        $this->linkCoachAthlete($coach, $athlete1);
        $this->linkCoachAthlete($coach, $athlete2);
        $this->createActiveAssignment($coach, $athlete1, $mesocycle);
        $this->createActiveAssignment($coach, $athlete2, $mesocycle);

        $oldCode = $mesocycle->getInviteCode();
        $mesocycleId = $mesocycle->getId();

        $client->loginUser($coach);

        // Load the show page to get the CSRF token for regenerate-code
        $crawler = $client->request('GET', '/mesocycles/'.$mesocycleId);
        $this->assertResponseIsSuccessful();

        $tokenInput = $crawler->filter('form[action="/mesocycles/'.$mesocycleId.'/regenerate-code"] input[name="_token"]');
        $this->assertGreaterThan(0, $tokenInput->count(), 'CSRF token input not found in regenerate-code form.');
        $csrfToken = $tokenInput->attr('value');

        $client->request('POST', '/mesocycles/'.$mesocycleId.'/regenerate-code', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/mesocycles/'.$mesocycleId);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        // All previously active assignments should be Cancelled
        $assignments = $em->getRepository(AssignedMesocycle::class)->findBy([
            'mesocycle' => $mesocycle,
        ]);
        $this->assertCount(2, $assignments);
        foreach ($assignments as $assignment) {
            $this->assertSame(
                AssignmentStatus::Cancelled,
                $assignment->getStatus(),
                'Active assignment should have been cancelled after code regeneration.'
            );
        }

        // The invite code should have changed
        $updatedMesocycle = $em->find(Mesocycle::class, $mesocycleId);
        $this->assertNotNull($updatedMesocycle);
        $this->assertNotSame($oldCode, $updatedMesocycle->getInviteCode(), 'Invite code should have been regenerated.');
    }

    // -------------------------------------------------------------------------
    // 9. testRegenerateCodeRequiresOwnership
    // -------------------------------------------------------------------------

    public function testRegenerateCodeRequiresOwnership(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $owner = $this->createCoach('owner.'.$suffix);
        $otherCoach = $this->createCoach('other.'.$suffix);
        $mesocycle = $this->createMesocycle($owner, 'MC Protected '.$suffix);

        $client->loginUser($otherCoach);
        $client->request('POST', '/mesocycles/'.$mesocycle->getId().'/regenerate-code', [
            '_token' => 'irrelevant-invalid-token',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    private function createWorkoutSession(Mesocycle $mesocycle, string $name = 'Sesión A', int $orderIndex = 1): WorkoutSession
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $session = new WorkoutSession();
        $session->setName($name);
        $session->setOrderIndex($orderIndex);
        $session->setMesocycle($mesocycle);

        $em->persist($session);
        $em->flush();

        return $session;
    }

    private function createExercise(string $suffix, User $createdBy): Exercise
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exercise = new Exercise();
        $exercise->setName('Ejercicio '.$suffix);
        $exercise->setCreatedBy($createdBy);

        $em->persist($exercise);
        $em->flush();

        return $exercise;
    }

    private function createSessionExercise(WorkoutSession $session, Exercise $exercise, int $orderIndex = 1): SessionExercise
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $se = new SessionExercise();
        $se->setWorkoutSession($session);
        $se->setExercise($exercise);
        $se->setOrderIndex($orderIndex);
        $se->setTargetSets(3);

        $em->persist($se);
        $em->flush();

        return $se;
    }

    // -------------------------------------------------------------------------
    // 10. testMyMesocyclesIndexShowsActiveAndPast
    // -------------------------------------------------------------------------

    public function testMyMesocyclesIndexShowsActiveAndPast(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $athlete = $this->createAthlete($suffix);
        $this->linkCoachAthlete($coach, $athlete);

        $activeMeso = $this->createMesocycle($coach, 'Active Meso '.$suffix);
        $cancelledMeso = $this->createMesocycle($coach, 'Cancelled Meso '.$suffix);

        $this->createActiveAssignment($coach, $athlete, $activeMeso);

        // Create a cancelled assignment
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $past = new AssignedMesocycle();
        $past->setAthlete($athlete);
        $past->setAssignedBy($coach);
        $past->setMesocycle($cancelledMeso);
        $past->setStartDate(new \DateTimeImmutable('-30 days'));
        $past->setStatus(AssignmentStatus::Cancelled);
        $em->persist($past);
        $em->flush();

        $client->loginUser($athlete);
        $client->request('GET', '/my-mesocycles');

        $this->assertResponseIsSuccessful();

        // Both mesocycle titles should appear on the page
        $this->assertSelectorTextContains('body', 'Active Meso '.$suffix);
        $this->assertSelectorTextContains('body', 'Cancelled Meso '.$suffix);

        // The "Mesociclos Activos" and "Historial" sections should both be present
        $this->assertSelectorExists('h2', 'Mesociclos Activos');
        $this->assertSelectorExists('h2', 'Historial de Mesociclos');
    }

    // -------------------------------------------------------------------------
    // 11. AC-2.3: Detail page renders for the owner (title, sessions, exercises)
    // -------------------------------------------------------------------------

    public function testShowRendersDetailForOwner(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $athlete = $this->createAthlete($suffix);
        $this->linkCoachAthlete($coach, $athlete);
        $mesocycle = $this->createMesocycle($coach, 'Show Test Meso '.$suffix);
        $assignment = $this->createActiveAssignment($coach, $athlete, $mesocycle);

        // Add a session with an exercise
        $session = $this->createWorkoutSession($mesocycle, 'Sesión Show '.$suffix);
        $exercise = $this->createExercise($suffix, $coach);
        $this->createSessionExercise($session, $exercise);

        // Clear the EM to flush the identity map so the controller gets a fresh load
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        $client->loginUser($athlete);
        $client->request('GET', '/my-mesocycles/'.$assignment->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Show Test Meso '.$suffix);
        $this->assertSelectorTextContains('body', 'Sesión Show '.$suffix);
        $this->assertSelectorTextContains('body', 'Ejercicio '.$suffix);
    }

    // -------------------------------------------------------------------------
    // 12. EC-2.1: IDOR — another athlete gets 404
    // -------------------------------------------------------------------------

    public function testShowReturns404ForAnotherAthlete(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $owner = $this->createAthlete('owner.'.$suffix);
        $intruder = $this->createAthlete('intruder.'.$suffix);
        $this->linkCoachAthlete($coach, $owner);
        $mesocycle = $this->createMesocycle($coach, 'IDOR Meso '.$suffix);
        $assignment = $this->createActiveAssignment($coach, $owner, $mesocycle);

        $client->loginUser($intruder);
        $client->request('GET', '/my-mesocycles/'.$assignment->getId());

        $this->assertResponseStatusCodeSame(404);
    }

    // -------------------------------------------------------------------------
    // 13. EC-2.2: Non-existent ID returns 404
    // -------------------------------------------------------------------------

    public function testShowReturns404ForNonExistentId(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $athlete = $this->createAthlete($suffix);

        $client->loginUser($athlete);
        $client->request('GET', '/my-mesocycles/999999999');

        $this->assertResponseStatusCodeSame(404);
    }

    // -------------------------------------------------------------------------
    // 14. EC-2.4: Coach (non-dual-role) gets 403 on the show route
    // -------------------------------------------------------------------------

    public function testShowReturns403ForPureCoach(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $athlete = $this->createAthlete($suffix);
        $this->linkCoachAthlete($coach, $athlete);
        $mesocycle = $this->createMesocycle($coach, 'Coach 403 Meso '.$suffix);
        $assignment = $this->createActiveAssignment($coach, $athlete, $mesocycle);

        $client->loginUser($coach);
        $client->request('GET', '/my-mesocycles/'.$assignment->getId());

        $this->assertResponseStatusCodeSame(403);
    }
}
