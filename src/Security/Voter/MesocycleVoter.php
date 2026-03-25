<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Mesocycle;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Controls edit and delete access to Mesocycle resources.
 *
 * Only the owning coach (the coach who created the mesocycle) may edit or delete it.
 *
 * @extends Voter<string, Mesocycle>
 */
class MesocycleVoter extends Voter
{
    public const MESOCYCLE_EDIT = 'MESOCYCLE_EDIT';
    public const MESOCYCLE_DELETE = 'MESOCYCLE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::MESOCYCLE_EDIT, self::MESOCYCLE_DELETE], true)
            && $subject instanceof Mesocycle;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        // Admins (ROLE_ADMIN) get full access
        if (in_array('ROLE_ADMIN', $currentUser->getRoles(), true)) {
            return true;
        }

        /** @var Mesocycle $mesocycle */
        $mesocycle = $subject;

        return $mesocycle->getCoach()->getId() === $currentUser->getId();
    }
}
