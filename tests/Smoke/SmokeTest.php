<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Smoke tests — verify every important route returns a non-500 status code.
 *
 * Uses fixture-like users created inline so the test suite is self-contained
 * and does not depend on doctrine:fixtures:load having been run.
 */
class SmokeTest extends WebTestCase
{
    private static ?User $athlete = null;
    private static ?User $coach = null;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getOrCreateAthlete(): User
    {
        if (null !== self::$athlete) {
            return self::$athlete;
        }

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        /** @var User|null $existing */
        $existing = $em->getRepository(User::class)->findOneBy(['email' => 'smoke.athlete@example.com']);
        if (null !== $existing) {
            self::$athlete = $existing;

            return $existing;
        }

        $user = new User();
        $user->setEmail('smoke.athlete@example.com');
        $user->setFirstName('Smoke');
        $user->setLastName('Athlete');
        $user->setRoles(['ROLE_ATLETA']);
        $user->setPassword($hasher->hashPassword($user, 'athlete123'));

        $em->persist($user);
        $em->flush();

        self::$athlete = $user;

        return $user;
    }

    private function getOrCreateCoach(): User
    {
        if (null !== self::$coach) {
            return self::$coach;
        }

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        /** @var User|null $existing */
        $existing = $em->getRepository(User::class)->findOneBy(['email' => 'smoke.coach@example.com']);
        if (null !== $existing) {
            self::$coach = $existing;

            return $existing;
        }

        $user = new User();
        $user->setEmail('smoke.coach@example.com');
        $user->setFirstName('Smoke');
        $user->setLastName('Coach');
        $user->setRoles(['ROLE_ENTRENADOR']);
        $user->setPassword($hasher->hashPassword($user, 'coach123'));

        $em->persist($user);
        $em->flush();

        self::$coach = $user;

        return $user;
    }

    // -------------------------------------------------------------------------
    // Unauthenticated — should redirect to login (302)
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('unauthenticatedRoutesProvider')]
    public function testUnauthenticatedRouteRedirectsToLogin(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertResponseRedirects(
            null,
            302,
            sprintf('Expected redirect for unauthenticated access to %s', $url)
        );

        $client->followRedirect();
        $this->assertStringContainsString('/login', (string) $client->getRequest()->getUri());
    }

    /** @return array<string, array{string}> */
    public static function unauthenticatedRoutesProvider(): array
    {
        return [
            'profile' => ['/profile'],
            'history' => ['/history'],
            'exercises' => ['/exercises'],
            'mesocycles' => ['/mesocycles'],
            'assignments' => ['/assignments'],
            'dashboard' => ['/dashboard'],
        ];
    }

    // -------------------------------------------------------------------------
    // As ROLE_ATLETA
    // -------------------------------------------------------------------------

    public function testAthleteCanAccessDashboard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getOrCreateAthlete());

        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful(
            'Athlete dashboard should return 200'
        );
    }

    public function testAthleteCanAccessProfile(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getOrCreateAthlete());

        $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful(
            'Athlete profile should return 200'
        );
    }

    public function testAthleteCanAccessHistory(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getOrCreateAthlete());

        $client->request('GET', '/history');

        $this->assertResponseIsSuccessful(
            'Training history should return 200 for athlete'
        );
    }

    // -------------------------------------------------------------------------
    // As ROLE_ENTRENADOR
    // -------------------------------------------------------------------------

    public function testCoachCanAccessDashboard(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getOrCreateCoach());

        // Unified dashboard now serves both roles
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful(
            'Coach dashboard should return 200'
        );
    }

    public function testCoachLegacyDashboardRedirects(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getOrCreateCoach());

        $client->request('GET', '/coach/dashboard');

        // Should redirect to /dashboard
        $this->assertResponseRedirects('/dashboard', 302, '/coach/dashboard should redirect to /dashboard');
    }

    public function testCoachCanAccessExercises(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getOrCreateCoach());

        $client->request('GET', '/exercises');

        $this->assertResponseIsSuccessful(
            'Exercise index should return 200 for coach'
        );
    }

    public function testCoachCanAccessMesocycles(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getOrCreateCoach());

        $client->request('GET', '/mesocycles');

        $this->assertResponseIsSuccessful(
            'Mesocycle index should return 200 for coach'
        );
    }

    public function testCoachCanAccessAssignments(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getOrCreateCoach());

        $client->request('GET', '/assignments');

        $this->assertResponseIsSuccessful(
            'Assignments index should return 200 for coach'
        );
    }

    // -------------------------------------------------------------------------
    // Cross-role access (athlete accessing coach-only routes)
    // -------------------------------------------------------------------------

    public function testAthleteCannotAccessExerciseIndex(): void
    {
        $client = static::createClient();
        $client->followRedirects(false);
        $client->loginUser($this->getOrCreateAthlete());

        $client->request('GET', '/exercises');

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains(
            $statusCode,
            [403, 302],
            sprintf('Athlete accessing /exercises should get 403 or redirect, got %d', $statusCode)
        );
    }

    public function testAthleteAccessingCoachDashboardIsRedirectedOrForbidden(): void
    {
        $client = static::createClient();
        $client->followRedirects(false);
        $client->loginUser($this->getOrCreateAthlete());

        $client->request('GET', '/coach/dashboard');

        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains(
            $statusCode,
            [403, 302],
            sprintf('Athlete accessing /coach/dashboard should get 403 or redirect, got %d', $statusCode)
        );
    }

    // -------------------------------------------------------------------------
    // Profile picture route
    // -------------------------------------------------------------------------

    public function testAthleteCanAccessProfilePicturePage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getOrCreateAthlete());

        $client->request('GET', '/profile/picture');

        $this->assertResponseIsSuccessful(
            'Profile picture page should return 200 for athlete'
        );
    }
}
