<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AssignedMesocycle;
use App\Entity\CoachAthlete;
use App\Entity\Exercise;
use App\Entity\Mesocycle;
use App\Entity\SessionExercise;
use App\Entity\User;
use App\Entity\WorkoutLog;
use App\Entity\WorkoutSession;
use App\Enum\AssignmentStatus;
use App\Enum\MeasurementType;
use App\Enum\SeriesType;
use App\Enum\WorkoutStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for TrainingHistoryController.
 *
 * Each test creates its own isolated data with unique suffixes to avoid
 * cross-test contamination without relying on database truncation.
 */
class TrainingHistoryControllerTest extends WebTestCase
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

    /**
     * Creates coach, athlete, CoachAthlete link, mesocycle, session, exercise,
     * session exercise, and assignment. Returns them all.
     */
    private function createFullSetup(string $suffix): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $coach = $this->createUser("coach.{$suffix}@hist.test", ['ROLE_ENTRENADOR']);
        $athlete = $this->createUser("athlete.{$suffix}@hist.test", ['ROLE_ATLETA']);

        $ca = new CoachAthlete();
        $ca->setCoach($coach);
        $ca->setAthlete($athlete);
        $em->persist($ca);

        $mesocycle = new Mesocycle();
        $mesocycle->setTitle('MC '.$suffix);
        $mesocycle->setDescription('');
        $mesocycle->setCoach($coach);
        $em->persist($mesocycle);

        $session = new WorkoutSession();
        $session->setName('Sesión Alpha');
        $session->setOrderIndex(1);
        $session->setMesocycle($mesocycle);
        $em->persist($session);

        $exercise = new Exercise();
        $exercise->setName('Sentadilla '.$suffix);
        $exercise->setDescription('');
        $exercise->setMeasurementType(MeasurementType::RepsWeight);
        $exercise->setCreatedBy($coach);
        $em->persist($exercise);

        $se = new SessionExercise();
        $se->setWorkoutSession($session);
        $se->setExercise($exercise);
        $se->setSeriesType(SeriesType::NormalTs);
        $se->setTargetSets(3);
        $se->setTargetReps(8);
        $se->setOrderIndex(1);
        $em->persist($se);

        $assignment = new AssignedMesocycle();
        $assignment->setMesocycle($mesocycle);
        $assignment->setAthlete($athlete);
        $assignment->setAssignedBy($coach);
        $assignment->setStartDate(new \DateTimeImmutable('today'));
        $assignment->setStatus(AssignmentStatus::Active);
        $em->persist($assignment);

        $em->flush();

        return compact('coach', 'athlete', 'session', 'se', 'assignment');
    }

    private function createCompletedLog(
        User $athlete,
        WorkoutSession $session,
        AssignedMesocycle $assignment,
    ): WorkoutLog {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $log = new WorkoutLog();
        $log->setAthlete($athlete);
        $log->setWorkoutSession($session);
        $log->setAssignedMesocycle($assignment);
        $log->setStatus(WorkoutStatus::Completed);
        $log->setEndTime(new \DateTimeImmutable('+1 hour'));
        $em->persist($log);
        $em->flush();

        return $log;
    }

    // -------------------------------------------------------------------------
    // Athlete: history list
    // -------------------------------------------------------------------------

    public function testAthleteCanSeeOwnHistoryList(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullSetup($suffix);

        $this->createCompletedLog($data['athlete'], $data['session'], $data['assignment']);

        $client->loginUser($data['athlete']);
        $client->request('GET', '/history');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mi Historial');
        $this->assertSelectorTextContains('tbody tr td:nth-child(2)', 'Sesión Alpha');
    }

    public function testUnauthenticatedCannotSeeHistory(): void
    {
        $client = static::createClient();
        $client->request('GET', '/history');

        // Redirects to login
        $this->assertResponseRedirects();
    }

    // -------------------------------------------------------------------------
    // Athlete: history detail
    // -------------------------------------------------------------------------

    public function testAthleteCanSeeOwnLogDetail(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullSetup($suffix);

        $log = $this->createCompletedLog($data['athlete'], $data['session'], $data['assignment']);

        $client->loginUser($data['athlete']);
        $client->request('GET', '/history/'.$log->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sesión Alpha');
    }

    public function testAthleteCannotSeeAnotherAthletesLogDetail(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullSetup($suffix);

        // Create a second unrelated athlete
        $otherAthlete = $this->createUser("other.{$suffix}@hist.test", ['ROLE_ATLETA']);

        $log = $this->createCompletedLog($data['athlete'], $data['session'], $data['assignment']);

        $client->loginUser($otherAthlete); // different athlete
        $client->request('GET', '/history/'.$log->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // Coach: athlete history
    // -------------------------------------------------------------------------

    public function testCoachCanSeeAssignedAthleteHistory(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullSetup($suffix);

        $this->createCompletedLog($data['athlete'], $data['session'], $data['assignment']);

        $client->loginUser($data['coach']);
        $client->request('GET', '/coach/athletes/'.$data['athlete']->getId().'/history');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Historial de');
        $this->assertSelectorTextContains('tbody tr td:nth-child(2)', 'Sesión Alpha');
    }

    public function testCoachCannotSeeNonAssignedAthleteHistory(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullSetup($suffix);

        // An athlete NOT linked to this coach
        $unlinkedAthlete = $this->createUser("unlinked.{$suffix}@hist.test", ['ROLE_ATLETA']);

        $client->loginUser($data['coach']);
        $client->request('GET', '/coach/athletes/'.$unlinkedAthlete->getId().'/history');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCoachCanSeeAssignedAthleteLogDetail(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullSetup($suffix);

        $log = $this->createCompletedLog($data['athlete'], $data['session'], $data['assignment']);

        $client->loginUser($data['coach']);
        $client->request(
            'GET',
            '/coach/athletes/'.$data['athlete']->getId().'/history/'.$log->getId()
        );

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sesión Alpha');
    }
}
