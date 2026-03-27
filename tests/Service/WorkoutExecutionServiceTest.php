<?php

declare(strict_types=1);

namespace App\Tests\Service;

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
use App\Repository\SessionExerciseRepository;
use App\Repository\SetLogRepository;
use App\Service\WorkoutExecutionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Unit / integration tests for WorkoutExecutionService.
 *
 * Each test runs inside a transaction that is rolled back in tearDown.
 */
class WorkoutExecutionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private WorkoutExecutionService $service;
    private SetLogRepository $setLogRepository;
    private SessionExerciseRepository $sessionExerciseRepository;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(WorkoutExecutionService::class);
        $this->setLogRepository = static::getContainer()->get(SetLogRepository::class);
        $this->sessionExerciseRepository = static::getContainer()->get(SessionExerciseRepository::class);
        $this->hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->clear();

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAthlete(string $suffix): User
    {
        $u = new User();
        $u->setEmail("athlete.{$suffix}@svc.test");
        $u->setFirstName('Test');
        $u->setLastName('Atleta');
        $u->setRoles(['ROLE_ATLETA']);
        $u->setPassword($this->hasher->hashPassword($u, 'pass'));
        $this->em->persist($u);

        return $u;
    }

    private function makeCoach(string $suffix): User
    {
        $u = new User();
        $u->setEmail("coach.{$suffix}@svc.test");
        $u->setFirstName('Test');
        $u->setLastName('Coach');
        $u->setRoles(['ROLE_ENTRENADOR']);
        $u->setPassword($this->hasher->hashPassword($u, 'pass'));
        $this->em->persist($u);

        return $u;
    }

    private function makeExercise(User $coach, string $name, MeasurementType $type): Exercise
    {
        $e = new Exercise();
        $e->setName($name);
        $e->setDescription('');
        $e->setMeasurementType($type);
        $e->setCreatedBy($coach);
        $this->em->persist($e);

        return $e;
    }

    private function makeSessionExercise(
        WorkoutSession $session,
        Exercise $exercise,
        SeriesType $seriesType,
        int $targetSets,
        int $orderIndex,
        ?int $superseriesGroup = null,
    ): SessionExercise {
        $se = new SessionExercise();
        $se->setWorkoutSession($session);
        $se->setExercise($exercise);
        $se->setSeriesType($seriesType);
        $se->setTargetSets($targetSets);
        $se->setOrderIndex($orderIndex);
        $se->setSuperseriesGroup($superseriesGroup);
        $this->em->persist($se);

        return $se;
    }

    private function makeWorkoutLog(User $athlete, WorkoutSession $session, AssignedMesocycle $assignment): WorkoutLog
    {
        $log = new WorkoutLog();
        $log->setAthlete($athlete);
        $log->setWorkoutSession($session);
        $log->setAssignedMesocycle($assignment);
        $log->setStatus(WorkoutStatus::InProgress);
        $this->em->persist($log);

        return $log;
    }

    private function makeFullStack(string $suffix, MeasurementType $measurementType = MeasurementType::RepsWeight): array
    {
        $coach = $this->makeCoach($suffix);
        $athlete = $this->makeAthlete($suffix);

        $ca = new CoachAthlete();
        $ca->setCoach($coach);
        $ca->setAthlete($athlete);
        $this->em->persist($ca);

        $mesocycle = new Mesocycle();
        $mesocycle->setTitle('Test MC '.$suffix);
        $mesocycle->setDescription('');
        $mesocycle->setCoach($coach);
        $this->em->persist($mesocycle);

        $session = new WorkoutSession();
        $session->setName('Session A');
        $session->setOrderIndex(1);
        $session->setMesocycle($mesocycle);
        $this->em->persist($session);

        $exercise = $this->makeExercise($coach, 'Press '.$suffix, $measurementType);
        $se1 = $this->makeSessionExercise($session, $exercise, SeriesType::NormalTs, 3, 1);

        $assignment = new AssignedMesocycle();
        $assignment->setMesocycle($mesocycle);
        $assignment->setAthlete($athlete);
        $assignment->setAssignedBy($coach);
        $assignment->setStartDate(new \DateTimeImmutable('today'));
        $assignment->setStatus(AssignmentStatus::Active);
        $this->em->persist($assignment);

        $this->em->flush();

        return compact('coach', 'athlete', 'session', 'exercise', 'se1', 'assignment', 'mesocycle');
    }

    // -------------------------------------------------------------------------
    // logSet — validation tests
    // -------------------------------------------------------------------------

    public function testLogSetRepsWeightRequiresReps(): void
    {
        $suffix = uniqid('', true);
        $data = $this->makeFullStack($suffix, MeasurementType::RepsWeight);
        $log = $this->makeWorkoutLog($data['athlete'], $data['session'], $data['assignment']);
        $this->em->flush();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->logSet($log, $data['se1'], [
            'weight' => 80.0,
        ]);
    }

    public function testLogSetRepsWeightRequiresPositiveReps(): void
    {
        $suffix = uniqid('', true);
        $data = $this->makeFullStack($suffix, MeasurementType::RepsWeight);
        $log = $this->makeWorkoutLog($data['athlete'], $data['session'], $data['assignment']);
        $this->em->flush();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->logSet($log, $data['se1'], [
            'reps' => 0,
            'weight' => 80.0,
        ]);
    }

    public function testLogSetTimeDistanceRequiresTimeDuration(): void
    {
        $suffix = uniqid('', true);
        $data = $this->makeFullStack($suffix, MeasurementType::TimeDistance);

        $log = $this->makeWorkoutLog($data['athlete'], $data['session'], $data['assignment']);
        $this->em->flush();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->logSet($log, $data['se1'], [
            'distance' => 1.5,
        ]);
    }

    public function testLogSetTimeKcalRequiresTimeDuration(): void
    {
        $suffix = uniqid('', true);
        $coach = $this->makeCoach($suffix);
        $athlete = $this->makeAthlete($suffix);

        $mesocycle = new Mesocycle();
        $mesocycle->setTitle('MC');
        $mesocycle->setDescription('');
        $mesocycle->setCoach($coach);
        $this->em->persist($mesocycle);

        $session = new WorkoutSession();
        $session->setName('S');
        $session->setOrderIndex(1);
        $session->setMesocycle($mesocycle);
        $this->em->persist($session);

        $exercise = $this->makeExercise($coach, 'Bike', MeasurementType::TimeKcal);
        $se = $this->makeSessionExercise($session, $exercise, SeriesType::Amrap, 3, 1);

        $assignment = new AssignedMesocycle();
        $assignment->setMesocycle($mesocycle);
        $assignment->setAthlete($athlete);
        $assignment->setAssignedBy($coach);
        $assignment->setStartDate(new \DateTimeImmutable('today'));
        $assignment->setStatus(AssignmentStatus::Active);
        $this->em->persist($assignment);

        $this->em->flush();

        $log = $this->makeWorkoutLog($athlete, $session, $assignment);
        $this->em->flush();

        $this->expectException(\InvalidArgumentException::class);

        $this->service->logSet($log, $se, ['kcal' => 100]);
    }

    // -------------------------------------------------------------------------
    // logSet — setNumber increment
    // -------------------------------------------------------------------------

    public function testLogSetIncrementsSetNumber(): void
    {
        $suffix = uniqid('', true);
        $data = $this->makeFullStack($suffix);
        $log = $this->makeWorkoutLog($data['athlete'], $data['session'], $data['assignment']);
        $log->setCurrentExerciseId($data['se1']->getId());
        $this->em->flush();

        $set1 = $this->service->logSet($log, $data['se1'], ['reps' => 8, 'weight' => 80.0]);
        $set2 = $this->service->logSet($log, $data['se1'], ['reps' => 8, 'weight' => 80.0]);
        $set3 = $this->service->logSet($log, $data['se1'], ['reps' => 7, 'weight' => 80.0]);

        $this->assertSame(1, $set1->getSetNumber());
        $this->assertSame(2, $set2->getSetNumber());
        $this->assertSame(3, $set3->getSetNumber());
    }

    // -------------------------------------------------------------------------
    // Locking — normal_ts advances after target sets
    // -------------------------------------------------------------------------

    public function testLockingNormalTsAdvancesAfterTargetSets(): void
    {
        $suffix = uniqid('', true);
        $coach = $this->makeCoach($suffix);
        $athlete = $this->makeAthlete($suffix);

        $mesocycle = new Mesocycle();
        $mesocycle->setTitle('MC');
        $mesocycle->setDescription('');
        $mesocycle->setCoach($coach);
        $this->em->persist($mesocycle);

        $session = new WorkoutSession();
        $session->setName('S');
        $session->setOrderIndex(1);
        $session->setMesocycle($mesocycle);
        $this->em->persist($session);

        $ex1 = $this->makeExercise($coach, 'Ex1 '.$suffix, MeasurementType::RepsWeight);
        $ex2 = $this->makeExercise($coach, 'Ex2 '.$suffix, MeasurementType::RepsWeight);

        // Two exercises, each with 2 target sets
        $se1 = $this->makeSessionExercise($session, $ex1, SeriesType::NormalTs, 2, 1);
        $se2 = $this->makeSessionExercise($session, $ex2, SeriesType::NormalTs, 2, 2);

        $assignment = new AssignedMesocycle();
        $assignment->setMesocycle($mesocycle);
        $assignment->setAthlete($athlete);
        $assignment->setAssignedBy($coach);
        $assignment->setStartDate(new \DateTimeImmutable('today'));
        $assignment->setStatus(AssignmentStatus::Active);
        $this->em->persist($assignment);

        $this->em->flush();

        $log = $this->makeWorkoutLog($athlete, $session, $assignment);
        $log->setCurrentExerciseId($se1->getId());
        $this->em->flush();

        // Log set 1 for se1 — still locked on se1 (1 < 2 target sets)
        $this->service->logSet($log, $se1, ['reps' => 8]);
        $this->em->refresh($log);
        $this->assertSame($se1->getId(), $log->getCurrentExerciseId(), 'Still on se1 after 1 set.');

        // Log set 2 for se1 — target met, should advance to se2
        $this->service->logSet($log, $se1, ['reps' => 8]);
        $this->em->refresh($log);
        $this->assertSame($se2->getId(), $log->getCurrentExerciseId(), 'Should advance to se2 after 2 sets.');
    }

    // -------------------------------------------------------------------------
    // Locking — superseries unlocks all in same group
    // -------------------------------------------------------------------------

    public function testLockingSupeseriesUnlocksGroup(): void
    {
        $suffix = uniqid('', true);
        $coach = $this->makeCoach($suffix);
        $athlete = $this->makeAthlete($suffix);

        $mesocycle = new Mesocycle();
        $mesocycle->setTitle('MC');
        $mesocycle->setDescription('');
        $mesocycle->setCoach($coach);
        $this->em->persist($mesocycle);

        $session = new WorkoutSession();
        $session->setName('S');
        $session->setOrderIndex(1);
        $session->setMesocycle($mesocycle);
        $this->em->persist($session);

        $ex1 = $this->makeExercise($coach, 'Super Ex1 '.$suffix, MeasurementType::RepsWeight);
        $ex2 = $this->makeExercise($coach, 'Super Ex2 '.$suffix, MeasurementType::RepsWeight);

        // Two superseries exercises in group 1, each 1 set target
        $se1 = $this->makeSessionExercise($session, $ex1, SeriesType::Superseries, 1, 1, 1);
        $se2 = $this->makeSessionExercise($session, $ex2, SeriesType::Superseries, 1, 2, 1);

        $assignment = new AssignedMesocycle();
        $assignment->setMesocycle($mesocycle);
        $assignment->setAthlete($athlete);
        $assignment->setAssignedBy($coach);
        $assignment->setStartDate(new \DateTimeImmutable('today'));
        $assignment->setStatus(AssignmentStatus::Active);
        $this->em->persist($assignment);

        $this->em->flush();

        // Start workout — first exercise is superseries, so currentExerciseId = null
        $log = $this->service->startWorkout($athlete, $assignment, $session);

        // currentExerciseId should be null for superseries at start
        $this->assertNull($log->getCurrentExerciseId(), 'Superseries should start with currentExerciseId = null.');

        // Log se1 (1 set = target) — se2 still needs its set, so stay null
        $this->service->logSet($log, $se1, ['reps' => 10]);
        $this->em->refresh($log);
        $this->assertNull($log->getCurrentExerciseId(), 'Still null until all group exercises done.');

        // Log se2 (1 set = target) — group done, no next exercise → null
        $this->service->logSet($log, $se2, ['reps' => 10]);
        $this->em->refresh($log);
        $this->assertNull($log->getCurrentExerciseId(), 'After group done with no next exercise, still null.');
    }
}
