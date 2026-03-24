<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkoutSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkoutSessionRepository::class)]
class WorkoutSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'integer')]
    private int $orderIndex = 0;

    /** Day of week: 1 = Monday … 7 = Sunday (ISO-8601). Nullable. */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $dayOfWeek = null;

    #[ORM\ManyToOne(targetEntity: Mesocycle::class, inversedBy: 'workoutSessions')]
    #[ORM\JoinColumn(nullable: false)]
    private Mesocycle $mesocycle;

    /**
     * @var Collection<int, SessionExercise>
     */
    #[ORM\OneToMany(
        targetEntity: SessionExercise::class,
        mappedBy: 'workoutSession',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $sessionExercises;

    /** @var SessionExercise[]|null Lazy cache for getOrderedExercises(). */
    private ?array $orderedExercisesCache = null;

    public function __construct()
    {
        $this->sessionExercises = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
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

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
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

    /**
     * @return Collection<int, SessionExercise>
     */
    public function getSessionExercises(): Collection
    {
        return $this->sessionExercises;
    }

    /**
     * Returns session exercises sorted ascending by orderIndex.
     *
     * The sorted result is cached on the instance so repeated calls within the
     * same request do not re-allocate and re-sort on every invocation.
     *
     * @return Collection<int, SessionExercise>
     */
    public function getOrderedExercises(): Collection
    {
        if ($this->orderedExercisesCache === null) {
            $exercises = $this->sessionExercises->toArray();
            usort($exercises, static fn (SessionExercise $a, SessionExercise $b): int => $a->getOrderIndex() <=> $b->getOrderIndex());
            $this->orderedExercisesCache = $exercises;
        }

        return new ArrayCollection($this->orderedExercisesCache);
    }

    public function addSessionExercise(SessionExercise $exercise): static
    {
        $this->orderedExercisesCache = null;
        if (!$this->sessionExercises->contains($exercise)) {
            $this->sessionExercises->add($exercise);
            $exercise->setWorkoutSession($this);
        }

        return $this;
    }

    public function removeSessionExercise(SessionExercise $exercise): static
    {
        $this->orderedExercisesCache = null;
        $this->sessionExercises->removeElement($exercise);

        return $this;
    }
}
