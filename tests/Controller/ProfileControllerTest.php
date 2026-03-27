<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AssignedMesocycle;
use App\Entity\BodyMeasurement;
use App\Entity\Mesocycle;
use App\Entity\User;
use App\Enum\AssignmentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for ProfileController.
 *
 * Each test creates isolated data with unique suffixes to avoid cross-test
 * contamination.
 */
class ProfileControllerTest extends WebTestCase
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

    private function createMeasurement(User $athlete, string $date = 'today', ?float $weight = 75.00): BodyMeasurement
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $m = new BodyMeasurement();
        $m->setAthlete($athlete);
        $m->setMeasurementDate(new \DateTimeImmutable($date));
        if (null !== $weight) {
            $m->setWeightKg($weight);
        }

        $em->persist($m);
        $em->flush();

        return $m;
    }

    // -------------------------------------------------------------------------
    // (a) GET /profile as athlete → 200
    // -------------------------------------------------------------------------

    public function testAthleteCanViewProfile(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@profile.test", ['ROLE_ATLETA']);

        $client->loginUser($athlete);
        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mi Perfil');
    }

    // -------------------------------------------------------------------------
    // (b) POST /profile/measurements/new with valid data → 302 + record in DB
    // -------------------------------------------------------------------------

    public function testAthleteCanCreateMeasurement(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@profile.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($athlete);
        $client->request('GET', '/profile/measurements/new');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Guardar medición', [
            'body_measurement[measurementDate]' => date('Y-m-d'),
            'body_measurement[weightKg]' => '80.50',
            'body_measurement[notes]' => 'Test fixture',
        ]);

        $this->assertResponseRedirects('/profile');

        $measurement = $em->getRepository(BodyMeasurement::class)->findOneBy(['athlete' => $athlete]);
        $this->assertNotNull($measurement);
        $this->assertEqualsWithDelta(80.50, $measurement->getWeightKg(), 0.001);
    }

    // -------------------------------------------------------------------------
    // (c) POST new with weight <= 0 → 200 (form re-render, no record)
    // -------------------------------------------------------------------------

    public function testCreateMeasurementWithZeroWeightFails(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@profile.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($athlete);
        $client->request('GET', '/profile/measurements/new');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Guardar medición', [
            'body_measurement[measurementDate]' => date('Y-m-d'),
            'body_measurement[weightKg]' => '0',
        ]);

        // Form is re-rendered with validation error (Symfony 6+ returns 422 for invalid form submissions)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [200, 422], true),
            "Expected 200 or 422, got {$statusCode}"
        );

        $count = $em->getRepository(BodyMeasurement::class)->count(['athlete' => $athlete]);
        $this->assertSame(0, $count);
    }

    // -------------------------------------------------------------------------
    // (d) POST new with no measurement fields → 200 (form re-render)
    // -------------------------------------------------------------------------

    public function testCreateMeasurementWithNoFieldsFailsValidation(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@profile.test", ['ROLE_ATLETA']);

        $client->loginUser($athlete);
        $client->request('GET', '/profile/measurements/new');
        $this->assertResponseIsSuccessful();

        $client->submitForm('Guardar medición', [
            'body_measurement[measurementDate]' => date('Y-m-d'),
            // all measurement fields empty
        ]);

        // Form is re-rendered (no redirect) — Symfony may return 200 or 422
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [200, 422], true),
            "Expected 200 or 422, got {$statusCode}"
        );
    }

    // -------------------------------------------------------------------------
    // (e) GET edit where id belongs to other athlete → 403
    // -------------------------------------------------------------------------

    public function testAthleteCannotEditAnotherAthletesMeasurement(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);

        $ownerAthlete = $this->createUser("owner.{$suffix}@profile.test", ['ROLE_ATLETA']);
        $otherAthlete = $this->createUser("other.{$suffix}@profile.test", ['ROLE_ATLETA']);

        $measurement = $this->createMeasurement($ownerAthlete);

        $client->loginUser($otherAthlete);
        $client->request('GET', '/profile/measurements/'.$measurement->getId().'/edit');

        $this->assertResponseStatusCodeSame(403);
    }

    // -------------------------------------------------------------------------
    // (f) POST delete with valid CSRF → 302 + record removed
    // -------------------------------------------------------------------------

    public function testAthleteCanDeleteOwnMeasurement(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@profile.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $measurement = $this->createMeasurement($athlete);
        $id = $measurement->getId();

        $client->loginUser($athlete);

        // Load the profile page first to establish a session and get a valid CSRF token
        $client->request('GET', '/profile');
        $this->assertResponseIsSuccessful();

        // Now fetch the JSON list endpoint to get the CSRF token for this measurement
        $client->request('GET', '/profile/measurements/list?page=1');
        $data = json_decode($client->getResponse()->getContent(), true);
        $token = $data['items'][0]['csrf_token'] ?? '';

        $client->request('POST', '/profile/measurements/'.$id.'/delete', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/profile');

        $em->clear();
        $this->assertNull($em->getRepository(BodyMeasurement::class)->find($id));
    }

    // -------------------------------------------------------------------------
    // (g) GET /profile unauthenticated → redirect to login
    // -------------------------------------------------------------------------

    public function testUnauthenticatedCannotAccessProfile(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');

        $this->assertResponseRedirects();
    }

    // -------------------------------------------------------------------------
    // Helpers for picture + mesocycle tests
    // -------------------------------------------------------------------------

    private function createCoachUser(string $suffix): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $u = new User();
        $u->setEmail("coach.{$suffix}@profile.test");
        $u->setFirstName('Coach');
        $u->setLastName('Test');
        $u->setRoles(['ROLE_ENTRENADOR']);
        $u->setPassword($hasher->hashPassword($u, 'pass123'));

        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function createMesocycleForCoach(User $coach, string $title = 'Mesociclo Test'): Mesocycle
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $m = new Mesocycle();
        $m->setTitle($title);
        $m->setCoach($coach);
        $em->persist($m);
        $em->flush();

        return $m;
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

    private function makeTestJpeg(): string
    {
        return __DIR__.'/fixtures/test_avatar.jpg';
    }

    // -------------------------------------------------------------------------
    // (h) GET /profile/picture as athlete → 200
    // -------------------------------------------------------------------------

    public function testAthleteCanAccessPicturePage(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@pic.test", ['ROLE_ATLETA']);

        $client->loginUser($athlete);
        $client->request('GET', '/profile/picture');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Foto de Perfil');
    }

    // -------------------------------------------------------------------------
    // (i) GET /profile/picture unauthenticated → redirect
    // -------------------------------------------------------------------------

    public function testUnauthenticatedCannotAccessPicturePage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile/picture');

        $this->assertResponseRedirects();
    }

    // -------------------------------------------------------------------------
    // (j) POST /profile/picture with valid JPEG → 302, file stored
    // -------------------------------------------------------------------------

    public function testAthleteCanUploadProfilePicture(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@pic.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($athlete);

        // GET the page first to set up the form
        $crawler = $client->request('GET', '/profile/picture');
        $this->assertResponseIsSuccessful();

        $fixturePath = $this->makeTestJpeg();
        $tmpPath = sys_get_temp_dir().'/test_avatar_'.uniqid().'.jpg';
        copy($fixturePath, $tmpPath);

        $uploadedFile = new UploadedFile($tmpPath, 'test_avatar.jpg', 'image/jpeg', null, true);

        // Submit the form with the file
        $form = $crawler->selectButton('Guardar foto')->form();
        $form['form[picture]']->upload($tmpPath);

        $client->submit($form);

        $this->assertResponseRedirects('/profile/picture');

        // Re-fetch from DB to verify persistence
        $em->clear();
        $freshAthlete = $em->getRepository(User::class)->find($athlete->getId());
        $this->assertNotNull($freshAthlete->getProfilePictureFilename(), 'Profile picture filename should be persisted');

        // Clean up uploaded file
        $uploadDir = static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/profile_pictures/';
        $uploadedPath = $uploadDir.$freshAthlete->getProfilePictureFilename();
        if (file_exists($uploadedPath)) {
            unlink($uploadedPath);
        }
    }

    // -------------------------------------------------------------------------
    // (k) POST /profile/picture with invalid MIME → 422
    // -------------------------------------------------------------------------

    public function testPictureUploadRejectsInvalidMime(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@pic.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($athlete);

        // GET the page first to set up the form with CSRF token
        $crawler = $client->request('GET', '/profile/picture');
        $this->assertResponseIsSuccessful();

        // Create a temporary text file
        $tmpPath = sys_get_temp_dir().'/fake_image_'.uniqid().'.txt';
        file_put_contents($tmpPath, 'This is not an image');

        // Submit the form with the text file
        $form = $crawler->selectButton('Guardar foto')->form();
        $form['form[picture]']->upload($tmpPath);

        $client->submit($form);

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [200, 422], true),
            "Expected 200 or 422 on validation failure, got {$statusCode}"
        );

        // Re-fetch from DB to verify no change
        $em->clear();
        $freshAthlete = $em->getRepository(User::class)->find($athlete->getId());
        $this->assertNull($freshAthlete->getProfilePictureFilename(), 'No picture should be saved on validation failure');

        if (file_exists($tmpPath)) {
            unlink($tmpPath);
        }
    }

    // -------------------------------------------------------------------------
    // (l) GET /profile shows active mesocycle link
    // -------------------------------------------------------------------------

    public function testProfileIndexShowsActiveMesocycle(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@meso.test", ['ROLE_ATLETA']);
        $coach = $this->createCoachUser($suffix);
        $mesocycle = $this->createMesocycleForCoach($coach, 'Fuerza Máxima '.$suffix);
        $assignment = $this->createActiveAssignment($coach, $athlete, $mesocycle);

        $client->loginUser($athlete);
        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Fuerza Máxima '.$suffix);

        // The active mesocycle link must point to athlete_mesocycle_show (not mesocycle_show)
        $expectedHref = '/my-mesocycles/'.$assignment->getId();
        $link = $crawler->filter('a[href="'.$expectedHref.'"]');
        $this->assertGreaterThan(0, $link->count(), 'Active mesocycle link should point to athlete_mesocycle_show route (/my-mesocycles/{id}).');
    }

    // -------------------------------------------------------------------------
    // (m) GET /profile without active mesocycle → shows "Sin mesociclo" message
    // -------------------------------------------------------------------------

    public function testProfileIndexShowsNoMesocycleMessage(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@nomeso.test", ['ROLE_ATLETA']);

        $client->loginUser($athlete);
        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Sin mesociclo activo');
    }

    // -------------------------------------------------------------------------
    // (n) GET /profile with measurement → shows latest measurement summary
    // -------------------------------------------------------------------------

    public function testProfileIndexShowsLatestMeasurement(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@meas.test", ['ROLE_ATLETA']);
        $this->createMeasurement($athlete, 'today', 82.50);

        $client->loginUser($athlete);
        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', '82.5');
    }

    // -------------------------------------------------------------------------
    // (p) POST /profile/picture with new image → old file deleted
    // -------------------------------------------------------------------------

    public function testUploadingNewPictureDeletesOldFile(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@pic.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $uploadDir = static::getContainer()->getParameter('kernel.project_dir').'/public/uploads/profile_pictures/';

        // Set a fake old picture on the user and create a dummy file for it
        $oldFilename = 'old_avatar_'.$suffix.'.jpg';
        $oldFilePath = $uploadDir.$oldFilename;
        file_put_contents($oldFilePath, 'fake old image content');

        $athlete->setProfilePictureFilename($oldFilename);
        $em->flush();

        $client->loginUser($athlete);

        // GET the form
        $crawler = $client->request('GET', '/profile/picture');
        $this->assertResponseIsSuccessful();

        // Upload a real new image
        $fixturePath = $this->makeTestJpeg();
        $tmpPath = sys_get_temp_dir().'/test_avatar_new_'.uniqid().'.jpg';
        copy($fixturePath, $tmpPath);

        $form = $crawler->selectButton('Guardar foto')->form();
        $form['form[picture]']->upload($tmpPath);
        $client->submit($form);

        $this->assertResponseRedirects('/profile/picture');

        // Old file should be gone
        $this->assertFileDoesNotExist($oldFilePath, 'Old profile picture file should have been deleted');

        // User should have a new filename
        $em->clear();
        $freshAthlete = $em->getRepository(User::class)->find($athlete->getId());
        $this->assertNotNull($freshAthlete->getProfilePictureFilename());
        $this->assertNotSame($oldFilename, $freshAthlete->getProfilePictureFilename(), 'Profile picture filename should have changed');

        // Clean up new uploaded file
        $newFilePath = $uploadDir.$freshAthlete->getProfilePictureFilename();
        if (file_exists($newFilePath)) {
            unlink($newFilePath);
        }
    }

    // -------------------------------------------------------------------------
    // (q) POST /profile/picture with oversized file → form error (200/422)
    // -------------------------------------------------------------------------

    public function testUploadPictureRejectsOversizedFile(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@pic.test", ['ROLE_ATLETA']);
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($athlete);

        // GET the form
        $crawler = $client->request('GET', '/profile/picture');
        $this->assertResponseIsSuccessful();

        // Create a real temp file > 2MB
        $tmpPath = sys_get_temp_dir().'/oversized_avatar_'.uniqid().'.jpg';
        file_put_contents($tmpPath, str_repeat('x', 3 * 1024 * 1024));

        $form = $crawler->selectButton('Guardar foto')->form();
        $form['form[picture]']->upload($tmpPath);
        $client->submit($form);

        // Form should be re-rendered with a validation error (not redirect)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [200, 422], true),
            "Expected 200 or 422 on oversized file, got {$statusCode}"
        );
        // Should not be a redirect (3xx) — both 200 and 422 satisfy this
        $this->assertFalse(
            $statusCode >= 300 && $statusCode < 400,
            'Response should not be a redirect on oversized file'
        );

        // User should have no profile picture stored
        $em->clear();
        $freshAthlete = $em->getRepository(User::class)->find($athlete->getId());
        $this->assertNull($freshAthlete->getProfilePictureFilename(), 'No picture should be saved for oversized file');

        if (file_exists($tmpPath)) {
            unlink($tmpPath);
        }
    }

    // -------------------------------------------------------------------------
    // (o) Navbar shows avatar <img> when picture is set
    // -------------------------------------------------------------------------

    public function testNavbarShowsAvatarWhenPictureSet(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@avatar.test", ['ROLE_ATLETA']);

        // Set a profile picture filename directly
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $athlete->setProfilePictureFilename('test-fake-uuid.jpg');
        $em->flush();

        $client->loginUser($athlete);
        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('<img', $content);
        $this->assertStringContainsString('test-fake-uuid.jpg', $content);
    }
}
