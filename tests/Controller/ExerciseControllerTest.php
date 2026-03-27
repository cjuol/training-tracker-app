<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Exercise;
use App\Entity\User;
use App\Enum\MeasurementType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for ExerciseController.
 *
 * Each test creates its own isolated users/exercises using unique e-mail suffixes
 * so tests can run in parallel or in any order without collisions.
 */
class ExerciseControllerTest extends WebTestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createCoach(string $suffix): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail("coach.{$suffix}@test.example");
        $user->setFirstName('Coach');
        $user->setLastName('Test');
        $user->setRoles(['ROLE_ENTRENADOR']);
        $user->setPassword($hasher->hashPassword($user, 'password123'));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createExercise(User $creator, string $name = 'Test Exercise'): Exercise
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $exercise = new Exercise();
        $exercise->setName($name);
        $exercise->setDescription('Test description');
        $exercise->setMeasurementType(MeasurementType::RepsWeight);
        $exercise->setCreatedBy($creator);

        $em->persist($exercise);
        $em->flush();

        return $exercise;
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function testIndexRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/exercises');

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function testIndexRequiresRoleEntrenador(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Create an athlete (ROLE_ATLETA only)
        $athlete = new User();
        $athlete->setEmail('athlete.'.uniqid('', true).'@test.example');
        $athlete->setFirstName('Atleta');
        $athlete->setLastName('Test');
        $athlete->setRoles(['ROLE_ATLETA']);
        $athlete->setPassword($hasher->hashPassword($athlete, 'password123'));
        $em->persist($athlete);
        $em->flush();

        $client->loginUser($athlete);
        $client->request('GET', '/exercises');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testIndexIsAccessibleByCoach(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));

        $client->loginUser($coach);
        $client->request('GET', '/exercises');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('table');
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function testCreateExercise(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));
        $client->loginUser($coach);

        $crawler = $client->request('GET', '/exercises/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Guardar')->form([
            'exercise[name]' => 'Curl de Bíceps',
            'exercise[description]' => 'Ejercicio de bíceps con mancuernas.',
            'exercise[measurementType]' => MeasurementType::RepsWeight->value,
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/exercises');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Curl de Bíceps');
    }

    // -------------------------------------------------------------------------
    // Edit
    // -------------------------------------------------------------------------

    public function testEditOwnExercise(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));
        $exercise = $this->createExercise($coach, 'Ejercicio Original');
        $client->loginUser($coach);

        $crawler = $client->request('GET', '/exercises/'.$exercise->getId().'/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Guardar')->form([
            'exercise[name]' => 'Ejercicio Editado',
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/exercises');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Ejercicio Editado');
    }

    public function testCannotEditAnotherUsersExercise(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $owner = $this->createCoach('owner.'.$suffix);
        $otherCoach = $this->createCoach('other.'.$suffix);
        $exercise = $this->createExercise($owner, 'Ejercicio del Propietario');

        // Log in as a different coach
        $client->loginUser($otherCoach);
        $client->request('GET', '/exercises/'.$exercise->getId().'/edit');

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function testDeleteWithValidCsrfToken(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));
        $exercise = $this->createExercise($coach, 'Ejercicio a Eliminar');
        $exerciseId = $exercise->getId();
        $client->loginUser($coach);

        // Load the index page first to establish a session.
        $crawler = $client->request('GET', '/exercises');
        $this->assertResponseIsSuccessful();

        // Find the delete form for this specific exercise by its action URL.
        $deleteFormSelector = 'form[action="/exercises/'.$exerciseId.'/delete"]';
        $tokenInput = $crawler->filter($deleteFormSelector.' input[name="_token"]');
        $this->assertGreaterThan(0, $tokenInput->count(), 'CSRF token input not found in delete form for exercise '.$exerciseId);
        $csrfToken = $tokenInput->attr('value');

        $client->request('POST', '/exercises/'.$exerciseId.'/delete', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/exercises');
        $client->followRedirect();
        // Verify the success flash message is shown
        $this->assertSelectorTextContains('body', 'Ejercicio eliminado correctamente');

        // Verify the exercise no longer exists in the database
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $deleted = $em->find(Exercise::class, $exerciseId);
        $this->assertNull($deleted, 'Exercise should have been deleted from the database.');
    }

    public function testCannotDeleteAnotherUsersExercise(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $owner = $this->createCoach('owner2.'.$suffix);
        $otherCoach = $this->createCoach('other2.'.$suffix);
        $exercise = $this->createExercise($owner, 'Ejercicio Protegido');

        $client->loginUser($otherCoach);

        // The voter check (403) happens before CSRF validation in our controller.
        // We send a request with a syntactically valid but incorrect token — the 403
        // must come from ExerciseVoter, not from CSRF failure (which would redirect).
        $client->request('POST', '/exercises/'.$exercise->getId().'/delete', [
            '_token' => 'invalid-token-for-403-test',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
