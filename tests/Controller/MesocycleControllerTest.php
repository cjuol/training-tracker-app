<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Mesocycle;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for MesocycleController.
 *
 * Each test creates isolated users/mesocycles with unique e-mail suffixes
 * so tests run in any order without collisions.
 */
class MesocycleControllerTest extends WebTestCase
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

    private function createMesocycle(User $coach, string $title = 'Test Mesocycle'): Mesocycle
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $mesocycle = new Mesocycle();
        $mesocycle->setTitle($title);
        $mesocycle->setDescription('Test description');
        $mesocycle->setCoach($coach);

        $em->persist($mesocycle);
        $em->flush();

        return $mesocycle;
    }

    // -------------------------------------------------------------------------
    // Index — access control
    // -------------------------------------------------------------------------

    public function testIndexRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mesocycles');

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }

    public function testIndexRequiresRoleEntrenador(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $athlete = new User();
        $athlete->setEmail('athlete.'.uniqid('', true).'@test.example');
        $athlete->setFirstName('Atleta');
        $athlete->setLastName('Test');
        $athlete->setRoles(['ROLE_ATLETA']);
        $athlete->setPassword($hasher->hashPassword($athlete, 'password123'));
        $em->persist($athlete);
        $em->flush();

        $client->loginUser($athlete);
        $client->request('GET', '/mesocycles');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testIndexIsAccessibleByCoach(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));
        $client->loginUser($coach);

        $client->request('GET', '/mesocycles');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mesociclos');
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    public function testCreateMesocycle(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));
        $client->loginUser($coach);

        $crawler = $client->request('GET', '/mesocycles/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Guardar')->form([
            'mesocycle[title]' => 'Mesociclo de Prueba',
            'mesocycle[description]' => 'Descripción de prueba.',
        ]);

        $client->submit($form);

        // After creation we redirect to show page
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Mesociclo de Prueba');
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function testShowMesocycle(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));
        $mesocycle = $this->createMesocycle($coach, 'Mesociclo Show Test');
        $client->loginUser($coach);

        $client->request('GET', '/mesocycles/'.$mesocycle->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mesociclo Show Test');
    }

    // -------------------------------------------------------------------------
    // Edit
    // -------------------------------------------------------------------------

    public function testEditOwnMesocycle(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));
        $mesocycle = $this->createMesocycle($coach, 'Mesociclo Original');
        $client->loginUser($coach);

        $crawler = $client->request('GET', '/mesocycles/'.$mesocycle->getId().'/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Guardar')->form([
            'mesocycle[title]' => 'Mesociclo Editado',
        ]);

        $client->submit($form);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Mesociclo Editado');
    }

    public function testCannotEditAnotherCoachMesocycle(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $owner = $this->createCoach('owner.'.$suffix);
        $otherCoach = $this->createCoach('other.'.$suffix);
        $mesocycle = $this->createMesocycle($owner, 'Mesociclo Ajeno');

        $client->loginUser($otherCoach);
        $client->request('GET', '/mesocycles/'.$mesocycle->getId().'/edit');

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    public function testDeleteWithCsrfToken(): void
    {
        $client = static::createClient();
        $coach = $this->createCoach(uniqid('', true));
        $mesocycle = $this->createMesocycle($coach, 'Mesociclo a Eliminar');
        $mesocycleId = $mesocycle->getId();
        $client->loginUser($coach);

        // Load index to establish session
        $crawler = $client->request('GET', '/mesocycles');
        $this->assertResponseIsSuccessful();

        // Find the CSRF token in the delete form for this mesocycle
        $deleteFormSelector = 'form[action="/mesocycles/'.$mesocycleId.'/delete"]';
        $tokenInput = $crawler->filter($deleteFormSelector.' input[name="_token"]');
        $this->assertGreaterThan(0, $tokenInput->count(), 'CSRF token input not found in delete form for mesocycle '.$mesocycleId);
        $csrfToken = $tokenInput->attr('value');

        $client->request('POST', '/mesocycles/'.$mesocycleId.'/delete', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/mesocycles');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Mesociclo eliminado correctamente');

        // Verify it no longer exists in the database
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $deleted = $em->find(Mesocycle::class, $mesocycleId);
        $this->assertNull($deleted, 'Mesocycle should have been deleted from the database.');
    }

    public function testCannotDeleteAnotherCoachMesocycle(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $owner = $this->createCoach('owner3.'.$suffix);
        $otherCoach = $this->createCoach('other3.'.$suffix);
        $mesocycle = $this->createMesocycle($owner, 'Mesociclo Protegido');

        $client->loginUser($otherCoach);
        $client->request('POST', '/mesocycles/'.$mesocycle->getId().'/delete', [
            '_token' => 'invalid-token-for-403-test',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
