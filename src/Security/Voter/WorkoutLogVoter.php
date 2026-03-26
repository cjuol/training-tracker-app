<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\WorkoutLog;
use App\Repository\CoachAthleteRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls access to WorkoutLog resources.
 *
 * Attributes:
 *   WORKOUT_VIEW    — owning athlete OR a coach who has the athlete assigned.
 *   WORKOUT_LOG_SET — only the owning athlete (only they record sets).
 *
 * @extends Voter<string, WorkoutLog>
 */
class WorkoutLogVoter extends Voter
{
    public const WORKOUT_VIEW = 'WORKOUT_VIEW';
    public const WORKOUT_LOG_SET = 'WORKOUT_LOG_SET';

    public function __construct(
        private readonly CoachAthleteRepository $coachAthleteRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::WORKOUT_VIEW, self::WORKOUT_LOG_SET], true)
            && $subject instanceof WorkoutLog;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var WorkoutLog $workoutLog */
        $workoutLog = $subject;

        return match ($attribute) {
            self::WORKOUT_VIEW => $this->canView($currentUser, $workoutLog),
            self::WORKOUT_LOG_SET => $this->canLogSet($currentUser, $workoutLog),
            default => false,
        };
    }

    private function canView(User $currentUser, WorkoutLog $workoutLog): bool
    {
        // The owning athlete can always view their own log
        if ($currentUser->getId() === $workoutLog->getAthlete()->getId()) {
            return true;
        }

        // A coach can view if the athlete is in their roster
        if (in_array('ROLE_ENTRENADOR', $currentUser->getRoles(), true)) {
            return $this->coachAthleteRepository->isAthleteOfCoach(
                $currentUser,
                $workoutLog->getAthlete()
            );
        }

        return false;
    }

    private function canLogSet(User $currentUser, WorkoutLog $workoutLog): bool
    {
        // Only the owning athlete may record sets
        return $currentUser->getId() === $workoutLog->getAthlete()->getId();
    }
}
