<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\WorkoutStatus;
use App\Repository\WorkoutLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkoutLogRepository::class)]
class WorkoutLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $athlete;

    #[ORM\ManyToOne(targetEntity: WorkoutSession::class)]
    #[ORM\JoinColumn(nullable: false)]
    private WorkoutSession $workoutSession;

    #[ORM\ManyToOne(targetEntity: AssignedMesocycle::class)]
    #[ORM\JoinColumn(nullable: false)]
    private AssignedMesocycle $assignedMesocycle;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(type: 'string', length: 20, enumType: WorkoutStatus::class)]
    private WorkoutStatus $status;

    /**
     * ID of the SessionExercise currently active/locked.
     * Null when inside a superseries group (all group exercises available simultaneously).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $currentExerciseId = null;

    /**
     * @var Collection<int, SetLog>
     */
    #[ORM\OneToMany(
        targetEntity: SetLog::class,
        mappedBy: 'workoutLog',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $setLogs;

    public function __construct()
    {
        $this->startTime = new \DateTimeImmutable();
        $this->status = WorkoutStatus::InProgress;
        $this->setLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getWorkoutSession(): WorkoutSession
    {
        return $this->workoutSession;
    }

    public function setWorkoutSession(WorkoutSession $workoutSession): static
    {
        $this->workoutSession = $workoutSession;

        return $this;
    }

    public function getAssignedMesocycle(): AssignedMesocycle
    {
        return $this->assignedMesocycle;
    }

    public function setAssignedMesocycle(AssignedMesocycle $assignedMesocycle): static
    {
        $this->assignedMesocycle = $assignedMesocycle;

        return $this;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getStatus(): WorkoutStatus
    {
        return $this->status;
    }

    public function setStatus(WorkoutStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCurrentExerciseId(): ?int
    {
        return $this->currentExerciseId;
    }

    public function setCurrentExerciseId(?int $currentExerciseId): static
    {
        $this->currentExerciseId = $currentExerciseId;

        return $this;
    }

    /**
     * @return Collection<int, SetLog>
     */
    public function getSetLogs(): Collection
    {
        return $this->setLogs;
    }

    public function addSetLog(SetLog $setLog): static
    {
        if (!$this->setLogs->contains($setLog)) {
            $this->setLogs->add($setLog);
            $setLog->setWorkoutLog($this);
        }

        return $this;
    }

    public function removeSetLog(SetLog $setLog): static
    {
        $this->setLogs->removeElement($setLog);

        return $this;
    }
}
