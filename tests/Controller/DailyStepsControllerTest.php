<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\DailySteps;
use App\Entity\Mesocycle;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for DailyStepsController + related features.
 */
class DailyStepsControllerTest extends WebTestCase
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

    private function createDailySteps(User $user, \DateTimeImmutable $date, int $steps): DailySteps
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $entry = new DailySteps();
        $entry->setUser($user);
        $entry->setDate($date);
        $entry->setSteps($steps);

        $em->persist($entry);
        $em->flush();

        return $entry;
    }

    // -------------------------------------------------------------------------
    // (1) GET /steps as authenticated user → 200
    // -------------------------------------------------------------------------

    public function testAuthenticatedUserCanAccessDailyStepsPage(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@steps.test", ['ROLE_ATLETA']);

        $client->loginUser($athlete);
        $client->request('GET', '/steps');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mis Pasos');
    }

    // -------------------------------------------------------------------------
    // (2) GET /steps unauthenticated → 302
    // -------------------------------------------------------------------------

    public function testUnauthenticatedUserCannotAccessDailySteps(): void
    {
        $client = static::createClient();
        $client->request('GET', '/steps');

        $this->assertResponseRedirects();
    }

    // -------------------------------------------------------------------------
    // (3) POST /steps with valid data → 302 redirect
    // -------------------------------------------------------------------------

    public function testAthleteCanLogSteps(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@steps.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($athlete);

        $crawler = $client->request('GET', '/steps');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Guardar')->form([
            'daily_steps[date]' => date('Y-m-d'),
            'daily_steps[steps]' => '8000',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/steps');

        $em->clear();
        $entries = $em->getRepository(DailySteps::class)->findBy(['user' => $athlete]);
        $this->assertCount(1, $entries);
        $this->assertSame(8000, $entries[0]->getSteps());
    }

    // -------------------------------------------------------------------------
    // (4) POST /steps twice same date → only 1 entry (upsert)
    // -------------------------------------------------------------------------

    public function testLoggingStepsTwiceOnSameDayUpdatesExistingEntry(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@steps2.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $today = new \DateTimeImmutable('today');
        $this->createDailySteps($athlete, $today, 5000);

        $client->loginUser($athlete);

        $crawler = $client->request('GET', '/steps');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Guardar')->form([
            'daily_steps[date]' => $today->format('Y-m-d'),
            'daily_steps[steps]' => '10000',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/steps');

        $em->clear();
        $entries = $em->getRepository(DailySteps::class)->findBy(['user' => $athlete]);
        $this->assertCount(1, $entries, 'Expected exactly 1 entry after upsert — no duplicates');
        $this->assertSame(10000, $entries[0]->getSteps(), 'Steps should have been updated to 10000');
    }

    // -------------------------------------------------------------------------
    // (5) Coach can also log steps → 302
    // -------------------------------------------------------------------------

    public function testCoachCanAlsoLogSteps(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $coach = $this->createUser("coach.{$suffix}@steps.test", ['ROLE_ENTRENADOR']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($coach);

        $crawler = $client->request('GET', '/steps');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Guardar')->form([
            'daily_steps[date]' => date('Y-m-d'),
            'daily_steps[steps]' => '6000',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/steps');

        $em->clear();
        $entries = $em->getRepository(DailySteps::class)->findBy(['user' => $coach]);
        $this->assertCount(1, $entries);
        $this->assertSame(6000, $entries[0]->getSteps());
    }

    // -------------------------------------------------------------------------
    // (6) DELETE own step entry → 302
    // -------------------------------------------------------------------------

    public function testAthleteCanDeleteOwnStepEntry(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@del.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $entry = $this->createDailySteps($athlete, new \DateTimeImmutable('today'), 7000);
        $id = $entry->getId();

        $client->loginUser($athlete);

        // Load page to get CSRF token
        $crawler = $client->request('GET', '/steps');
        $this->assertResponseIsSuccessful();

        // Find the CSRF token from the delete form
        $tokenInput = $crawler->filter('form[action="/steps/'.$id.'/delete"] input[name="_token"]');
        $this->assertGreaterThan(0, $tokenInput->count(), 'Delete form CSRF token not found');
        $csrfToken = $tokenInput->attr('value');

        $client->request('POST', '/steps/'.$id.'/delete', ['_token' => $csrfToken]);

        $this->assertResponseRedirects('/steps');

        $em->clear();
        $this->assertNull($em->getRepository(DailySteps::class)->find($id));
    }

    // -------------------------------------------------------------------------
    // (7) Profile picture delete clears filename
    // -------------------------------------------------------------------------

    public function testProfilePictureDeleteClearsFilename(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@pic.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Give the user a fake picture filename (no actual file needed for this test)
        $athlete->setProfilePictureFilename('fake_pic_'.$suffix.'.jpg');
        $em->flush();

        $client->loginUser($athlete);

        // Get the picture page to get a valid CSRF token
        $crawler = $client->request('GET', '/profile/picture');
        $this->assertResponseIsSuccessful();

        $tokenInput = $crawler->filter('form[action="/profile/picture/delete"] input[name="_token"]');
        $this->assertGreaterThan(0, $tokenInput->count(), 'Delete picture form CSRF token not found');
        $csrfToken = $tokenInput->attr('value');

        $client->request('POST', '/profile/picture/delete', ['_token' => $csrfToken]);

        $this->assertResponseRedirects('/profile/picture');

        $em->clear();
        $freshAthlete = $em->getRepository(User::class)->find($athlete->getId());
        $this->assertNull($freshAthlete->getProfilePictureFilename(), 'Profile picture filename should be null after deletion');
    }

    // -------------------------------------------------------------------------
    // (8) Mesocycle form accepts dailyStepsTarget → saved correctly
    // -------------------------------------------------------------------------

    public function testMesocycleFormAcceptsDailyStepsTarget(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $coach = $this->createUser("coach.{$suffix}@meso.test", ['ROLE_ENTRENADOR']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($coach);

        $crawler = $client->request('GET', '/mesocycles/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Guardar')->form([
            'mesocycle[title]' => 'Meso con pasos '.$suffix,
            'mesocycle[description]' => 'Test',
            'mesocycle[dailyStepsTarget]' => '9000',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects();

        $em->clear();
        $mesocycle = $em->getRepository(Mesocycle::class)->findOneBy(['title' => 'Meso con pasos '.$suffix]);
        $this->assertNotNull($mesocycle, 'Mesocycle should have been created');
        $this->assertSame(9000, $mesocycle->getDailyStepsTarget(), 'dailyStepsTarget should be 9000');
    }
}
