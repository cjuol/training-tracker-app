<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AssignedMesocycle;
use App\Entity\CoachAthlete;
use App\Entity\Mesocycle;
use App\Entity\User;
use App\Enum\AssignmentStatus;
use App\Repository\AssignedMesocycleRepository;
use App\Repository\CoachAthleteRepository;
use App\Repository\MesocycleRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles mesocycle assignment business logic.
 *
 * Ownership validation is done here (not only in the form) because the form
 * filter is a UX convenience — server-side validation is the authoritative
 * security gate against crafted requests that bypass the form.
 */
class AssignmentService
{
    public function __construct(
        private readonly CoachAthleteRepository $coachAthleteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MesocycleRepository $mesocycleRepository,
        private readonly AssignedMesocycleRepository $assignedMesocycleRepository,
    ) {}


    /**
     * Assigns a mesocycle to an athlete on behalf of a coach.
     *
     * @throws \DomainException if the athlete is not in the coach's roster
     */
    public function assign(
        User $coach,
        User $athlete,
        Mesocycle $mesocycle,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
    ): AssignedMesocycle {
        if ($mesocycle->getCoach()->getId() !== $coach->getId()) {
            throw new \DomainException('Este mesociclo no pertenece al entrenador especificado.');
        }

        if (!$this->coachAthleteRepository->isAthleteOfCoach($coach, $athlete)) {
            throw new \DomainException(sprintf('El atleta "%s" no pertenece al equipo del entrenador "%s".', $athlete->getEmail(), $coach->getEmail()));
        }

        $assignment = new AssignedMesocycle();
        $assignment->setAthlete($athlete);
        $assignment->setMesocycle($mesocycle);
        $assignment->setAssignedBy($coach);
        $assignment->setStartDate($startDate);
        $assignment->setEndDate($endDate);

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        return $assignment;
    }

    /**
     * Joins an athlete to a mesocycle using an invite code.
     * Auto-creates the CoachAthlete relationship if it does not exist.
     *
     * @throws \DomainException if the code is invalid or the athlete is already assigned
     */
    public function joinByCode(User $athlete, string $code, \DateTimeImmutable $startDate): AssignedMesocycle
    {
        $mesocycle = $this->mesocycleRepository->findOneByInviteCode(trim($code));
        if ($mesocycle === null) {
            throw new \DomainException('Código de invitación inválido.');
        }
        if ($this->assignedMesocycleRepository->findActiveByAthleteAndMesocycle($athlete, $mesocycle) !== null) {
            throw new \DomainException('Ya tienes este mesociclo asignado y activo.');
        }
        $coach = $mesocycle->getCoach();
        if (!$this->coachAthleteRepository->isAthleteOfCoach($coach, $athlete)) {
            $coachAthlete = new CoachAthlete();
            $coachAthlete->setCoach($coach);
            $coachAthlete->setAthlete($athlete);
            $this->entityManager->persist($coachAthlete);
        }
        $assignment = new AssignedMesocycle();
        $assignment->setMesocycle($mesocycle);
        $assignment->setAthlete($athlete);
        $assignment->setAssignedBy($coach);
        $assignment->setStartDate($startDate);
        $this->entityManager->persist($assignment);
        $this->entityManager->flush();
        return $assignment;
    }

    /**
     * Cancels all active assignments for the mesocycle and regenerates its invite code.
     */
    public function regenerateCode(Mesocycle $mesocycle): void
    {
        foreach ($this->assignedMesocycleRepository->findActiveByMesocycle($mesocycle) as $assignment) {
            $assignment->setStatus(AssignmentStatus::Cancelled);
        }

        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $mesocycle->regenerateInviteCode();
            try {
                $this->entityManager->flush();
                return;
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                if ($attempt === $maxAttempts) {
                    throw new \RuntimeException('No se pudo generar un código único tras '.$maxAttempts.' intentos.', 0, $e);
                }
                // retry with a new code
            }
        }
    }
}
