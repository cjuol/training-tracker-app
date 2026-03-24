<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SetLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SetLogRepository::class)]
class SetLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: WorkoutLog::class, inversedBy: 'setLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private WorkoutLog $workoutLog;

    #[ORM\ManyToOne(targetEntity: SessionExercise::class)]
    #[ORM\JoinColumn(nullable: false)]
    private SessionExercise $sessionExercise;

    #[ORM\Column(type: 'integer')]
    private int $setNumber;

    // --- Strength fields (reps_weight) ---

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $reps = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $rir = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $weight = null;

    // --- Cardio fields ---

    /** Duration in seconds */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $timeDuration = null;

    /** Distance in km */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $distance = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $kcal = null;

    // --- Control fields ---

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $restTimeSeconds = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacion = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $loggedAt;

    public function __construct()
    {
        $this->loggedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkoutLog(): WorkoutLog
    {
        return $this->workoutLog;
    }

    public function setWorkoutLog(WorkoutLog $workoutLog): static
    {
        $this->workoutLog = $workoutLog;

        return $this;
    }

    public function getSessionExercise(): SessionExercise
    {
        return $this->sessionExercise;
    }

    public function setSessionExercise(SessionExercise $sessionExercise): static
    {
        $this->sessionExercise = $sessionExercise;

        return $this;
    }

    public function getSetNumber(): int
    {
        return $this->setNumber;
    }

    public function setSetNumber(int $setNumber): static
    {
        $this->setNumber = $setNumber;

        return $this;
    }

    public function getReps(): ?int
    {
        return $this->reps;
    }

    public function setReps(?int $reps): static
    {
        $this->reps = $reps;

        return $this;
    }

    public function getRir(): ?int
    {
        return $this->rir;
    }

    public function setRir(?int $rir): static
    {
        $this->rir = $rir;

        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getTimeDuration(): ?int
    {
        return $this->timeDuration;
    }

    public function setTimeDuration(?int $timeDuration): static
    {
        $this->timeDuration = $timeDuration;

        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(?float $distance): static
    {
        $this->distance = $distance;

        return $this;
    }

    public function getKcal(): ?int
    {
        return $this->kcal;
    }

    public function setKcal(?int $kcal): static
    {
        $this->kcal = $kcal;

        return $this;
    }

    public function getRestTimeSeconds(): ?int
    {
        return $this->restTimeSeconds;
    }

    public function setRestTimeSeconds(?int $restTimeSeconds): static
    {
        $this->restTimeSeconds = $restTimeSeconds;

        return $this;
    }

    public function getObservacion(): ?string
    {
        return $this->observacion;
    }

    public function setObservacion(?string $observacion): static
    {
        $this->observacion = $observacion;

        return $this;
    }

    public function getLoggedAt(): \DateTimeImmutable
    {
        return $this->loggedAt;
    }
}
