<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\BodyMeasurement;
use App\Entity\User;
use App\Repository\CoachAthleteRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls access to BodyMeasurement resources.
 *
 * Attributes:
 *   MEASUREMENT_VIEW   — owning athlete OR a coach who has the athlete assigned (read-only for coach).
 *   MEASUREMENT_EDIT   — only the owning athlete (ownership by user ID, not role).
 *   MEASUREMENT_DELETE — only the owning athlete (ownership by user ID, not role).
 *
 * Note: A user may hold both ROLE_ATLETA and ROLE_ENTRENADOR simultaneously.
 * Ownership checks use identity (getId()) not role, so a coach-athlete can manage their own records.
 *
 * @extends Voter<string, BodyMeasurement>
 */
class BodyMeasurementVoter extends Voter
{
    public const MEASUREMENT_VIEW = 'MEASUREMENT_VIEW';
    public const MEASUREMENT_EDIT = 'MEASUREMENT_EDIT';
    public const MEASUREMENT_DELETE = 'MEASUREMENT_DELETE';

    public function __construct(
        private readonly CoachAthleteRepository $coachAthleteRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::MEASUREMENT_VIEW, self::MEASUREMENT_EDIT, self::MEASUREMENT_DELETE], true)
            && $subject instanceof BodyMeasurement;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var BodyMeasurement $measurement */
        $measurement = $subject;

        return match ($attribute) {
            self::MEASUREMENT_VIEW => $this->canView($currentUser, $measurement),
            self::MEASUREMENT_EDIT => $this->isOwner($currentUser, $measurement),
            self::MEASUREMENT_DELETE => $this->isOwner($currentUser, $measurement),
            default => false,
        };
    }

    private function canView(User $currentUser, BodyMeasurement $measurement): bool
    {
        // The owning athlete can always view their own measurements
        if ($this->isOwner($currentUser, $measurement)) {
            return true;
        }

        // A coach can view if the athlete is in their roster
        if (in_array('ROLE_ENTRENADOR', $currentUser->getRoles(), true)) {
            return $this->coachAthleteRepository->isAthleteOfCoach(
                $currentUser,
                $measurement->getAthlete()
            );
        }

        return false;
    }

    private function isOwner(User $currentUser, BodyMeasurement $measurement): bool
    {
        // Check by user identity (ID), not by role
        return $currentUser->getId() === $measurement->getAthlete()->getId();
    }
}
