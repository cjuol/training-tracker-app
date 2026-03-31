<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SeriesType;
use App\Repository\SessionExerciseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SessionExerciseRepository::class)]
class SessionExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $orderIndex = 0;

    #[ORM\Column(type: 'string', length: 50, enumType: SeriesType::class)]
    private SeriesType $seriesType = SeriesType::NormalTs;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $targetSets = 3;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $targetReps = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $targetWeight = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $restSeconds = null;

    /**
     * Groups exercises that together form a superseries or triset.
     * Nullable int — exercises sharing the same non-null value belong to the same group.
     * Using a plain int instead of a relation avoids unnecessary join-table complexity
     * while still allowing arbitrary N-way groupings within a session.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $superseriesGroup = null;

    #[ORM\ManyToOne(targetEntity: WorkoutSession::class, inversedBy: 'sessionExercises')]
    #[ORM\JoinColumn(nullable: false)]
    private WorkoutSession $workoutSession;

    #[ORM\ManyToOne(targetEntity: Exercise::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Exercise $exercise;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): static
    {
        $this->orderIndex = $orderIndex;

        return $this;
    }

    public function getSeriesType(): SeriesType
    {
        return $this->seriesType;
    }

    public function setSeriesType(SeriesType $seriesType): static
    {
        $this->seriesType = $seriesType;

        return $this;
    }

    public function getTargetSets(): int
    {
        return $this->targetSets;
    }

    public function setTargetSets(int $targetSets): static
    {
        $this->targetSets = $targetSets;

        return $this;
    }

    public function getTargetReps(): ?int
    {
        return $this->targetReps;
    }

    public function setTargetReps(?int $targetReps): static
    {
        $this->targetReps = $targetReps;

        return $this;
    }

    public function getTargetWeight(): ?float
    {
        return $this->targetWeight;
    }

    public function setTargetWeight(?float $targetWeight): static
    {
        $this->targetWeight = $targetWeight;

        return $this;
    }

    public function getRestSeconds(): ?int
    {
        return $this->restSeconds;
    }

    public function setRestSeconds(?int $restSeconds): static
    {
        $this->restSeconds = $restSeconds;

        return $this;
    }

    public function getSuperseriesGroup(): ?int
    {
        return $this->superseriesGroup;
    }

    public function setSuperseriesGroup(?int $superseriesGroup): static
    {
        $this->superseriesGroup = $superseriesGroup;

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

    public function getExercise(): Exercise
    {
        return $this->exercise;
    }

    public function setExercise(Exercise $exercise): static
    {
        $this->exercise = $exercise;

        return $this;
    }
}
