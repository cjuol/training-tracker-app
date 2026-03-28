<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FitbitActivityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FitbitActivityRepository::class)]
#[ORM\Table(name: 'fitbit_activity')]
#[ORM\UniqueConstraint(name: 'uq_fitbit_activity_user_log', columns: ['user_id', 'fitbit_log_id'])]
class FitbitActivity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 64)]
    private string $fitbitLogId = '';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'string', length: 150)]
    private string $name = '';

    #[ORM\Column]
    private int $durationMinutes = 0;

    #[ORM\Column(nullable: true)]
    private ?int $calories = null;

    #[ORM\Column(nullable: true)]
    private ?int $steps = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $distance = null;

    #[ORM\Column(nullable: true)]
    private ?int $averageHeartRate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getFitbitLogId(): string
    {
        return $this->fitbitLogId;
    }

    public function setFitbitLogId(string $fitbitLogId): static
    {
        $this->fitbitLogId = $fitbitLogId;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
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

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getCalories(): ?int
    {
        return $this->calories;
    }

    public function setCalories(?int $calories): static
    {
        $this->calories = $calories;

        return $this;
    }

    public function getSteps(): ?int
    {
        return $this->steps;
    }

    public function setSteps(?int $steps): static
    {
        $this->steps = $steps;

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

    public function getAverageHeartRate(): ?int
    {
        return $this->averageHeartRate;
    }

    public function setAverageHeartRate(?int $averageHeartRate): static
    {
        $this->averageHeartRate = $averageHeartRate;

        return $this;
    }
}
