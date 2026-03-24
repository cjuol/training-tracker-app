<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Repository\CoachAthleteRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls access to User resources.
 *
 * Attributes:
 *   VIEW — user can view themselves; a coach can view any of their athletes.
 *   EDIT — user can edit themselves; coaches cannot edit athlete profiles.
 *
 * @extends Voter<string, User>
 */
class UserVoter extends Voter
{
    public const VIEW = 'USER_VIEW';
    public const EDIT = 'USER_EDIT';

    // NOTE: USER_VIEW / USER_EDIT are not currently called from any controller
    // or template. The voter is kept for safety but is effectively unused.
    public function __construct(private readonly CoachAthleteRepository $coachAthleteRepository)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($currentUser, $targetUser),
            self::EDIT => $this->canEdit($currentUser, $targetUser),
            default => false,
        };
    }

    private function canView(User $currentUser, User $targetUser): bool
    {
        // A user can always view themselves
        if ($currentUser === $targetUser) {
            return true;
        }

        // A coach can view any athlete they have linked via CoachAthlete
        if (in_array('ROLE_ENTRENADOR', $currentUser->getRoles(), true)) {
            return $this->coachAthleteRepository->isAthleteOfCoach($currentUser, $targetUser);
        }

        return false;
    }

    private function canEdit(User $currentUser, User $targetUser): bool
    {
        // Only a user can edit their own profile
        return $currentUser === $targetUser;
    }
}
