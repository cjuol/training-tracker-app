<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Exercise;
use App\Entity\Mesocycle;
use App\Entity\SessionExercise;
use App\Entity\User;
use App\Entity\WorkoutSession;
use App\Enum\MeasurementType;
use App\Enum\SeriesType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for SessionExerciseController.
 *
 * Each test creates isolated users/mesocycles with unique e-mail suffixes
 * so tests run in any order without collisions.
 */
class SessionExerciseControllerTest extends WebTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCoach(string $suffix): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail("coach.{$suffix}@se.test");
        $user->setFirstName('Coach');
        $user->setLastName('Test');
        $user->setRoles(['ROLE_ENTRENADOR']);
        $user->setPassword($hasher->hashPassword($user, 'password123'));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createMesocycle(User $coach, string $suffix): Mesocycle
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $mesocycle = new Mesocycle();
        $mesocycle->setTitle('Mesociclo '.$suffix);
        $mesocycle->setDescription('');
        $mesocycle->setCoach($coach);

        $em->persist($mesocycle);
        $em->flush();

        return $mesocycle;
    }

    private function createSession(Mesocycle $mesocycle, string $name, int $orderIndex): WorkoutSession
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

    private function createSessionExercise(WorkoutSession $session, User $coach, string $suffix): SessionExercise
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exercise = new Exercise();
        $exercise->setName('Ejercicio '.$suffix);
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

        $em->flush();

        return $se;
    }

    /**
     * Loads the mesocycle show page and extracts the move CSRF token from
     * the data-sortable-movecsrf-value attribute rendered in _session_card.html.twig.
     */
    private function getMoveCsrfToken(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, Mesocycle $mesocycle): string
    {
        $crawler = $client->request('GET', '/mesocycles/'.$mesocycle->getId());
        $this->assertResponseIsSuccessful();

        $token = $crawler->filter('[data-sortable-movecsrf-value]')->attr('data-sortable-movecsrf-value');
        $this->assertNotEmpty($token, 'Move CSRF token not found on mesocycle show page.');

        return $token;
    }

    // -------------------------------------------------------------------------
    // move — happy path
    // -------------------------------------------------------------------------

    public function testMoveExerciseToAnotherSessionInSameMesocycle(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $mesocycle = $this->createMesocycle($coach, $suffix);
        $sessionA = $this->createSession($mesocycle, 'Sesión A', 1);
        $sessionB = $this->createSession($mesocycle, 'Sesión B', 2);
        $se = $this->createSessionExercise($sessionA, $coach, $suffix);

        $client->loginUser($coach);
        $csrfToken = $this->getMoveCsrfToken($client, $mesocycle);

        $client->request(
            'POST',
            '/sessions/'.$sessionB->getId().'/exercises/'.$se->getId().'/move',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['_token' => $csrfToken])
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        // Verify the exercise is now linked to session B in the database
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updatedSe = $em->find(SessionExercise::class, $se->getId());
        $this->assertNotNull($updatedSe);
        $this->assertSame($sessionB->getId(), $updatedSe->getWorkoutSession()->getId());
    }

    // -------------------------------------------------------------------------
    // move — invalid CSRF token
    // -------------------------------------------------------------------------

    public function testMoveWithInvalidCsrfTokenReturns403(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $mesocycle = $this->createMesocycle($coach, $suffix);
        $sessionA = $this->createSession($mesocycle, 'Sesión A', 1);
        $sessionB = $this->createSession($mesocycle, 'Sesión B', 2);
        $se = $this->createSessionExercise($sessionA, $coach, $suffix);

        $client->loginUser($coach);

        $client->request(
            'POST',
            '/sessions/'.$sessionB->getId().'/exercises/'.$se->getId().'/move',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['_token' => 'invalid-csrf-token'])
        );

        $this->assertResponseStatusCodeSame(403);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    // -------------------------------------------------------------------------
    // move — cross-mesocycle rejection
    // -------------------------------------------------------------------------

    public function testMoveExerciseToDifferentMesocycleReturns403(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $mesocycleA = $this->createMesocycle($coach, $suffix.'-A');
        $mesocycleB = $this->createMesocycle($coach, $suffix.'-B');
        $sessionA = $this->createSession($mesocycleA, 'Sesión A', 1);
        $sessionB = $this->createSession($mesocycleB, 'Sesión B', 1);
        $se = $this->createSessionExercise($sessionA, $coach, $suffix);

        $client->loginUser($coach);
        $csrfToken = $this->getMoveCsrfToken($client, $mesocycleA);

        $client->request(
            'POST',
            '/sessions/'.$sessionB->getId().'/exercises/'.$se->getId().'/move',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['_token' => $csrfToken])
        );

        $this->assertResponseStatusCodeSame(403);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $response);
    }

    // -------------------------------------------------------------------------
    // move — unauthenticated
    // -------------------------------------------------------------------------

    public function testMoveWithoutAuthenticationRedirectsToLogin(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $coach = $this->createCoach($suffix);
        $mesocycle = $this->createMesocycle($coach, $suffix);
        $sessionA = $this->createSession($mesocycle, 'Sesión A', 1);
        $sessionB = $this->createSession($mesocycle, 'Sesión B', 2);
        $se = $this->createSessionExercise($sessionA, $coach, $suffix);

        $client->request(
            'POST',
            '/sessions/'.$sessionB->getId().'/exercises/'.$se->getId().'/move',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['_token' => 'any-token'])
        );

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }
}
