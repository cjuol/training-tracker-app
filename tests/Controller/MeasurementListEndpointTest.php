<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\BodyMeasurement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Verifies the JSON shape of GET /profile/measurements/list?page=1.
 */
class MeasurementListEndpointTest extends WebTestCase
{
    private function createUser(string $email, array $roles): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $u = new User();
        $u->setEmail($email);
        $u->setFirstName('Test');
        $u->setLastName('User');
        $u->setRoles($roles);
        $u->setPassword($hasher->hashPassword($u, 'pass123'));

        $em->persist($u);
        $em->flush();

        return $u;
    }

    private function createMeasurement(User $athlete, string $date, ?float $weight): BodyMeasurement
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $m = new BodyMeasurement();
        $m->setAthlete($athlete);
        $m->setMeasurementDate(new \DateTimeImmutable($date));
        if (null !== $weight) {
            $m->setWeightKg($weight);
        }
        $m->setChestCm(95.00);
        $m->setNotes('Test note');
        $em->persist($m);
        $em->flush();

        return $m;
    }

    public function testListEndpointReturnsCorrectJsonShape(): void
    {
        $client = static::createClient();
        $suffix = uniqid('', true);
        $athlete = $this->createUser("athlete.{$suffix}@list.test", ['ROLE_ATLETA']);

        $this->createMeasurement($athlete, '2026-03-10', 75.00);
        $this->createMeasurement($athlete, '2026-03-20', 74.50);

        $client->loginUser($athlete);
        $client->request('GET', '/profile/measurements/list?page=1', [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        // Top-level keys
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('perPage', $data);
        $this->assertArrayHasKey('hasMore', $data);

        // Page values
        $this->assertSame(1, $data['page']);
        $this->assertSame(10, $data['perPage']);
        $this->assertSame(2, $data['total']);
        $this->assertFalse($data['hasMore']);

        // Item shape
        $this->assertCount(2, $data['items']);
        $firstItem = $data['items'][0]; // Most recent first

        $this->assertArrayHasKey('id', $firstItem);
        $this->assertArrayHasKey('measurement_date', $firstItem);
        $this->assertArrayHasKey('weight_kg', $firstItem);
        $this->assertArrayHasKey('chest_cm', $firstItem);
        $this->assertArrayHasKey('waist_cm', $firstItem);
        $this->assertArrayHasKey('hips_cm', $firstItem);
        $this->assertArrayHasKey('arms_cm', $firstItem);
        $this->assertArrayHasKey('notes', $firstItem);
        $this->assertArrayHasKey('csrf_token', $firstItem);

        // Verify measurement_date is ISO string
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $firstItem['measurement_date']);

        // Most recent (2026-03-20) should be first
        $this->assertSame('2026-03-20', $firstItem['measurement_date']);
    }
}
