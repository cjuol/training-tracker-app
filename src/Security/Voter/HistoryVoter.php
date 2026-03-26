<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\WorkoutLog;
use App\Repository\CoachAthleteRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls read access to training history (WorkoutLog records).
 *
 * Attribute: HISTORY_VIEW
 *   Granted to the owning athlete OR a coach who has that athlete assigned.
 *
 * Why a separate voter from WorkoutLogVoter?
 *   WorkoutLogVoter also carries WORKOUT_LOG_SET (write / execution concerns).
 *   HistoryVoter is the read-only history gate used by TrainingHistoryController,
 *   keeping authorization intent explicit and each voter single-purpose.
 *
 * @extends Voter<string, WorkoutLog>
 */
class HistoryVoter extends Voter
{
    public const HISTORY_VIEW = 'HISTORY_VIEW';

    public function __construct(
        private readonly CoachAthleteRepository $coachAthleteRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::HISTORY_VIEW === $attribute
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

        // The owning athlete can always view their own history
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
}
