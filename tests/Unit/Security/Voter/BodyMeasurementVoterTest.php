<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\BodyMeasurement;
use App\Entity\User;
use App\Repository\CoachAthleteRepository;
use App\Security\Voter\BodyMeasurementVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Unit tests for BodyMeasurementVoter.
 *
 * All 8 cases defined in the spec are covered:
 *   (a) owner VIEW → GRANT
 *   (b) owner EDIT → GRANT
 *   (c) owner DELETE → GRANT
 *   (d) coach VIEW on assigned athlete → GRANT
 *   (e) coach VIEW on non-assigned athlete → DENY
 *   (f) coach EDIT → DENY
 *   (g) coach DELETE → DENY
 *   (h) unauthenticated user → DENY
 */
class BodyMeasurementVoterTest extends TestCase
{
    private CoachAthleteRepository&MockObject $coachAthleteRepo;
    private BodyMeasurementVoter $voter;

    protected function setUp(): void
    {
        $this->coachAthleteRepo = $this->createMock(CoachAthleteRepository::class);
        $this->voter = new BodyMeasurementVoter($this->coachAthleteRepo);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(int $id, array $roles = ['ROLE_ATLETA']): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getRoles')->willReturn(array_unique([...$roles, 'ROLE_USER']));

        return $user;
    }

    private function makeMeasurement(User $owner): BodyMeasurement
    {
        $measurement = $this->createMock(BodyMeasurement::class);
        $measurement->method('getAthlete')->willReturn($owner);

        return $measurement;
    }

    private function makeToken(mixed $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function vote(string $attribute, ?User $currentUser, BodyMeasurement $measurement): int
    {
        $token = $this->makeToken($currentUser);

        return $this->voter->vote($token, $measurement, [$attribute]);
    }

    // -------------------------------------------------------------------------
    // (a) owner VIEW → GRANT
    // -------------------------------------------------------------------------

    public function testOwnerCanView(): void
    {
        $athlete = $this->makeUser(1);
        $measurement = $this->makeMeasurement($athlete);

        $result = $this->vote(BodyMeasurementVoter::MEASUREMENT_VIEW, $athlete, $measurement);

        $this->assertSame(1, $result); // ACCESS_GRANTED
    }

    // -------------------------------------------------------------------------
    // (b) owner EDIT → GRANT
    // -------------------------------------------------------------------------

    public function testOwnerCanEdit(): void
    {
        $athlete = $this->makeUser(1);
        $measurement = $this->makeMeasurement($athlete);

        $result = $this->vote(BodyMeasurementVoter::MEASUREMENT_EDIT, $athlete, $measurement);

        $this->assertSame(1, $result);
    }

    // -------------------------------------------------------------------------
    // (c) owner DELETE → GRANT
    // -------------------------------------------------------------------------

    public function testOwnerCanDelete(): void
    {
        $athlete = $this->makeUser(1);
        $measurement = $this->makeMeasurement($athlete);

        $result = $this->vote(BodyMeasurementVoter::MEASUREMENT_DELETE, $athlete, $measurement);

        $this->assertSame(1, $result);
    }

    // -------------------------------------------------------------------------
    // (d) coach VIEW on assigned athlete → GRANT
    // -------------------------------------------------------------------------

    public function testCoachCanViewAssignedAthletesMeasurement(): void
    {
        $athlete = $this->makeUser(2, ['ROLE_ATLETA']);
        $coach = $this->makeUser(10, ['ROLE_ENTRENADOR']);

        $measurement = $this->makeMeasurement($athlete);

        $this->coachAthleteRepo
            ->expects($this->once())
            ->method('isAthleteOfCoach')
            ->with($coach, $athlete)
            ->willReturn(true);

        $result = $this->vote(BodyMeasurementVoter::MEASUREMENT_VIEW, $coach, $measurement);

        $this->assertSame(1, $result);
    }

    // -------------------------------------------------------------------------
    // (e) coach VIEW on non-assigned athlete → DENY
    // -------------------------------------------------------------------------

    public function testCoachCannotViewNonAssignedAthletesMeasurement(): void
    {
        $athlete = $this->makeUser(2, ['ROLE_ATLETA']);
        $coach = $this->makeUser(10, ['ROLE_ENTRENADOR']);

        $measurement = $this->makeMeasurement($athlete);

        $this->coachAthleteRepo
            ->expects($this->once())
            ->method('isAthleteOfCoach')
            ->with($coach, $athlete)
            ->willReturn(false);

        $result = $this->vote(BodyMeasurementVoter::MEASUREMENT_VIEW, $coach, $measurement);

        $this->assertSame(-1, $result); // ACCESS_DENIED
    }

    // -------------------------------------------------------------------------
    // (f) coach EDIT → DENY
    // -------------------------------------------------------------------------

    public function testCoachCannotEditMeasurement(): void
    {
        $athlete = $this->makeUser(2, ['ROLE_ATLETA']);
        $coach = $this->makeUser(10, ['ROLE_ENTRENADOR']);

        $measurement = $this->makeMeasurement($athlete);

        // isAthleteOfCoach should NOT be called for EDIT
        $this->coachAthleteRepo->expects($this->never())->method('isAthleteOfCoach');

        $result = $this->vote(BodyMeasurementVoter::MEASUREMENT_EDIT, $coach, $measurement);

        $this->assertSame(-1, $result);
    }

    // -------------------------------------------------------------------------
    // (g) coach DELETE → DENY
    // -------------------------------------------------------------------------

    public function testCoachCannotDeleteMeasurement(): void
    {
        $athlete = $this->makeUser(2, ['ROLE_ATLETA']);
        $coach = $this->makeUser(10, ['ROLE_ENTRENADOR']);

        $measurement = $this->makeMeasurement($athlete);

        $this->coachAthleteRepo->expects($this->never())->method('isAthleteOfCoach');

        $result = $this->vote(BodyMeasurementVoter::MEASUREMENT_DELETE, $coach, $measurement);

        $this->assertSame(-1, $result);
    }

    // -------------------------------------------------------------------------
    // (h) unauthenticated user → DENY
    // -------------------------------------------------------------------------

    public function testUnauthenticatedUserIsDenied(): void
    {
        $athlete = $this->makeUser(1);
        $measurement = $this->makeMeasurement($athlete);

        // Token returns non-User object (anonymous)
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $result = $this->voter->vote($token, $measurement, [BodyMeasurementVoter::MEASUREMENT_VIEW]);

        $this->assertSame(-1, $result);
    }
}
