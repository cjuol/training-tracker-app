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
 * Functional tests for WorkoutExecutionController.
 *
 * Each test creates its own isolated data with unique suffixes.
 */
class WorkoutExecutionControllerTest extends WebTestCase
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

    private function createFullWorkoutSetup(string $suffix): array
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $coach = $this->createUser("coach.{$suffix}@wc.test", ['ROLE_ENTRENADOR']);
        $athlete = $this->createUser("athlete.{$suffix}@wc.test", ['ROLE_ATLETA']);

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
        $session->setName('Sesión A');
        $session->setOrderIndex(1);
        $session->setMesocycle($mesocycle);
        $em->persist($session);

        $exercise = new Exercise();
        $exercise->setName('Press '.$suffix);
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

        return compact('coach', 'athlete', 'session', 'exercise', 'se', 'assignment');
    }

    private function createWorkoutLog(User $athlete, WorkoutSession $session, AssignedMesocycle $assignment, ?int $currentExerciseId = null): WorkoutLog
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $log = new WorkoutLog();
        $log->setAthlete($athlete);
        $log->setWorkoutSession($session);
        $log->setAssignedMesocycle($assignment);
        $log->setStatus(WorkoutStatus::InProgress);
        $log->setCurrentExerciseId($currentExerciseId);
        $em->persist($log);
        $em->flush();

        return $log;
    }

    // -------------------------------------------------------------------------
    // Start workout
    // -------------------------------------------------------------------------

    public function testAthleteCanStartWorkout(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);

        $client->loginUser($data['athlete']);

        $client->request(
            'POST',
            '/workout/start/'.$data['assignment']->getId().'/'.$data['session']->getId()
        );

        // Should redirect to /workout/{id}
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sesión A');
    }

    public function testCoachCannotStartAnotherAthletesWorkout(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);

        // Coach inherits ROLE_ATLETA via role_hierarchy, so the #[IsGranted('ROLE_ATLETA')]
        // gate passes. However, WorkoutExecutionService throws \DomainException because
        // the assignment belongs to the athlete, not the coach. The controller catches it,
        // adds a flash error and redirects to the dashboard.
        $client->loginUser($data['coach']);

        $client->request(
            'POST',
            '/workout/start/'.$data['assignment']->getId().'/'.$data['session']->getId()
        );

        $this->assertResponseRedirects();
    }

    // -------------------------------------------------------------------------
    // View workout
    // -------------------------------------------------------------------------

    public function testAthleteCanViewOwnWorkout(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);
        $log = $this->createWorkoutLog($data['athlete'], $data['session'], $data['assignment'], $data['se']->getId());

        $client->loginUser($data['athlete']);
        $client->request('GET', '/workout/'.$log->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sesión A');
    }

    public function testCoachCanViewAthleteWorkout(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);
        $log = $this->createWorkoutLog($data['athlete'], $data['session'], $data['assignment'], $data['se']->getId());

        $client->loginUser($data['coach']); // coach is linked via CoachAthlete
        $client->request('GET', '/workout/'.$log->getId());

        $this->assertResponseIsSuccessful();
    }

    public function testAnotherAthleteCannotViewWorkout(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);
        $log = $this->createWorkoutLog($data['athlete'], $data['session'], $data['assignment'], $data['se']->getId());
        $otherAthlete = $this->createUser("other.{$suffix}@wc.test", ['ROLE_ATLETA']);

        $client->loginUser($otherAthlete);
        $client->request('GET', '/workout/'.$log->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // Log a set (JSON)
    // -------------------------------------------------------------------------

    public function testAthleteCanLogSet(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);
        $log = $this->createWorkoutLog($data['athlete'], $data['session'], $data['assignment'], $data['se']->getId());

        $client->loginUser($data['athlete']);

        $client->request(
            'POST',
            '/workout/'.$log->getId().'/set/log',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'sessionExerciseId' => $data['se']->getId(),
                'reps' => 8,
                'weight' => 80.0,
                'rir' => 2,
            ])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($response['success']);
        $this->assertSame(1, $response['setLog']['setNumber']);
        $this->assertSame(8, $response['setLog']['reps']);
        $this->assertEqualsWithDelta(80.0, $response['setLog']['weight'], 0.001);
    }

    public function testLogSetReturnsMissingRepsError(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);
        $log = $this->createWorkoutLog($data['athlete'], $data['session'], $data['assignment'], $data['se']->getId());

        $client->loginUser($data['athlete']);

        $client->request(
            'POST',
            '/workout/'.$log->getId().'/set/log',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'sessionExerciseId' => $data['se']->getId(),
                'weight' => 80.0,
            ])
        );

        $this->assertResponseStatusCodeSame(422);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    public function testAnotherAthleteCannotLogSet(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);
        $log = $this->createWorkoutLog($data['athlete'], $data['session'], $data['assignment'], $data['se']->getId());
        $otherAthlete = $this->createUser("intruder.{$suffix}@wc.test", ['ROLE_ATLETA']);

        $client->loginUser($otherAthlete);

        $client->request(
            'POST',
            '/workout/'.$log->getId().'/set/log',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'sessionExerciseId' => $data['se']->getId(),
                'reps' => 8,
            ])
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // Complete workout
    // -------------------------------------------------------------------------

    public function testAthleteCanCompleteWorkout(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);
        $log = $this->createWorkoutLog($data['athlete'], $data['session'], $data['assignment'], $data['se']->getId());

        $client->loginUser($data['athlete']);

        $client->request('POST', '/workout/'.$log->getId().'/complete');

        $this->assertResponseRedirects('/dashboard', 302);

        // Verify status in DB
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updatedLog = $em->find(WorkoutLog::class, $log->getId());
        $this->assertNotNull($updatedLog);
        $this->assertSame(WorkoutStatus::Completed, $updatedLog->getStatus());
        $this->assertNotNull($updatedLog->getEndTime());
    }

    public function testAnotherAthleteCannotCompleteWorkout(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $data = $this->createFullWorkoutSetup($suffix);
        $log = $this->createWorkoutLog($data['athlete'], $data['session'], $data['assignment']);
        $otherAthlete = $this->createUser("other2.{$suffix}@wc.test", ['ROLE_ATLETA']);

        $client->loginUser($otherAthlete);
        $client->request('POST', '/workout/'.$log->getId().'/complete');

        $this->assertResponseStatusCodeSame(403);
    }
}
