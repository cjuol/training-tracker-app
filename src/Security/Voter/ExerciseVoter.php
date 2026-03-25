<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Exercise;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls edit and delete access to Exercise resources.
 *
 * Only the creator of an exercise (or an admin) may edit or delete it.
 *
 * @extends Voter<string, Exercise>
 */
class ExerciseVoter extends Voter
{
    public const EXERCISE_EDIT = 'EXERCISE_EDIT';
    public const EXERCISE_DELETE = 'EXERCISE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EXERCISE_EDIT, self::EXERCISE_DELETE], true)
            && $subject instanceof Exercise;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var Exercise $exercise */
        $exercise = $subject;

        // Admins (ROLE_ADMIN) get full access
        if (in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return true;
        }

        // Only the creator can edit or delete
        return $exercise->getCreatedBy()->getId() === $currentUser->getId();
    }
}
