<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AssignmentStatus;
use App\Repository\AssignedMesocycleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AssignedMesocycleRepository::class)]
class AssignedMesocycle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Mesocycle::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Mesocycle $mesocycle;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $athlete;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $assignedBy;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: 'string', length: 20, enumType: AssignmentStatus::class)]
    private AssignmentStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->status = AssignmentStatus::Active;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMesocycle(): Mesocycle
    {
        return $this->mesocycle;
    }

    public function setMesocycle(Mesocycle $mesocycle): static
    {
        $this->mesocycle = $mesocycle;

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

    public function getAssignedBy(): User
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(User $assignedBy): static
    {
        $this->assignedBy = $assignedBy;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): AssignmentStatus
    {
        return $this->status;
    }

    public function setStatus(AssignmentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
