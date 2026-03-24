<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CoachAthleteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Explicit join entity (instead of self-referential ManyToMany on User) to allow
 * future extensibility: e.g., adding a `since` date, a `status` field, or notes
 * about the coach–athlete relationship without touching the User entity.
 */
#[ORM\Entity(repositoryClass: CoachAthleteRepository::class)]
#[ORM\Table(name: 'coach_athlete')]
#[ORM\UniqueConstraint(name: 'uq_coach_athlete', columns: ['coach_id', 'athlete_id'])]
class CoachAthlete
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'coachRelations')]
    #[ORM\JoinColumn(name: 'coach_id', referencedColumnName: 'id', nullable: false)]
    private User $coach;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'athleteRelations')]
    #[ORM\JoinColumn(name: 'athlete_id', referencedColumnName: 'id', nullable: false)]
    private User $athlete;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCoach(): User
    {
        return $this->coach;
    }

    public function setCoach(User $coach): static
    {
        $this->coach = $coach;

        return $this;
    }

    public function getAthlete(): User
    {
        return $this->athlete;
    }

    public function setAthlete(User $athlete): static
    {
        $this->athlete = $athlete;

        return $this;
    }
}
