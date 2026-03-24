<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MesocycleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MesocycleRepository::class)]
class Mesocycle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $coach;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, WorkoutSession>
     */
    #[ORM\OneToMany(
        targetEntity: WorkoutSession::class,
        mappedBy: 'mesocycle',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $workoutSessions;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $dailyStepsTarget = null;

    #[ORM\Column(type: 'string', length: 12, unique: true)]
    private string $inviteCode;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->workoutSessions = new ArrayCollection();
        // Collision probability: 1/2^48 — negligible at MVP scale.
        // If a UniqueConstraintViolationException occurs on persist, the caller should retry.
        $this->inviteCode = bin2hex(random_bytes(6));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, WorkoutSession>
     */
    public function getWorkoutSessions(): Collection
    {
        return $this->workoutSessions;
    }

    public function addWorkoutSession(WorkoutSession $session): static
    {
        if (!$this->workoutSessions->contains($session)) {
            $this->workoutSessions->add($session);
            $session->setMesocycle($this);
        }

        return $this;
    }

    public function removeWorkoutSession(WorkoutSession $session): static
    {
        if ($this->workoutSessions->removeElement($session)) {
            // orphanRemoval handles the DELETE
        }

        return $this;
    }

    public function getDailyStepsTarget(): ?int
    {
        return $this->dailyStepsTarget;
    }

    public function setDailyStepsTarget(?int $dailyStepsTarget): static
    {
        $this->dailyStepsTarget = $dailyStepsTarget;

        return $this;
    }

    public function getInviteCode(): string
    {
        return $this->inviteCode;
    }

    public function regenerateInviteCode(): void
    {
        $this->inviteCode = bin2hex(random_bytes(6));
    }
}
