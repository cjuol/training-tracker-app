<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DailyHeartRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyHeartRateRepository::class)]
#[ORM\Table(name: 'daily_heart_rate')]
#[ORM\UniqueConstraint(name: 'uq_daily_heart_rate_user_date', columns: ['user_id', 'date'])]
class DailyHeartRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(nullable: true)]
    private ?int $restingHeartRate = null;

    #[ORM\Column(nullable: true)]
    private ?int $caloriesOut = null;

    /** @var array<mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $zones = null;

    /** @var array<array{time: string, value: int}>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $intradayData = null;

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

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getRestingHeartRate(): ?int
    {
        return $this->restingHeartRate;
    }

    public function setRestingHeartRate(?int $restingHeartRate): static
    {
        $this->restingHeartRate = $restingHeartRate;

        return $this;
    }

    public function getCaloriesOut(): ?int
    {
        return $this->caloriesOut;
    }

    public function setCaloriesOut(?int $caloriesOut): static
    {
        $this->caloriesOut = $caloriesOut;

        return $this;
    }

    public function getZones(): ?array
    {
        return $this->zones;
    }

    public function setZones(?array $zones): static
    {
        $this->zones = $zones;

        return $this;
    }

    public function getIntradayData(): ?array
    {
        return $this->intradayData;
    }

    public function setIntradayData(?array $intradayData): static
    {
        $this->intradayData = $intradayData;

        return $this;
    }
}
