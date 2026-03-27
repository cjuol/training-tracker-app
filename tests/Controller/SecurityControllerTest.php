<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for the login/logout flow.
 *
 * Tests use the test database (configured via when@test in doctrine.yaml).
 */
class SecurityControllerTest extends WebTestCase
{
    private function createTestUser(string $email, string $password, array $roles = ['ROLE_ATLETA']): User
    {
        // Assumes kernel is already booted (i.e., createClient() was called before this helper)
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
    }

    public function testLoginWithValidCredentials(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $this->createTestUser("testlogin.{$suffix}@example.com", 'testpassword123');

        $crawler = $client->request('GET', '/login');

        $form = $crawler->filter('form')->form([
            '_username' => "testlogin.{$suffix}@example.com",
            '_password' => 'testpassword123',
        ]);

        $client->submit($form);

        // After successful login, should redirect to dashboard
        $this->assertResponseRedirects('/dashboard');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        $form = $crawler->filter('form')->form([
            '_username' => 'nonexistent@example.com',
            '_password' => 'wrongpassword',
        ]);

        $client->submit($form);

        // Should redirect back to login page on failure
        $this->assertResponseRedirects('/login');

        $client->followRedirect();
        // Error message should appear
        $this->assertSelectorExists('[class*="red"]');
    }

    public function testLogoutRedirectsToLoginPage(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $user = $this->createTestUser("logout.{$suffix}@example.com", 'testpassword123');

        // Log in first
        $client->loginUser($user);

        // Access dashboard to confirm we're logged in
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        // Logout
        $client->request('GET', '/logout');
        $this->assertResponseRedirects('/login');
    }

    public function testProtectedRouteRedirectsToLoginWhenUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        // Should redirect to login page
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertRouteSame('app_login');
    }
}
