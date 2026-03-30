<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DailyWellnessMetricsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyWellnessMetricsRepository::class)]
#[ORM\Table(name: 'daily_wellness_metrics')]
#[ORM\UniqueConstraint(name: 'uq_wellness_user_date', columns: ['user_id', 'date'])]
class DailyWellnessMetrics
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

    /** Nightly average HRV RMSSD (ms) from Fitbit HRV daily summary */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rmssd = null;

    /** Deep-sleep RMSSD (ms) from Fitbit HRV daily summary */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $deepRmssd = null;

    /** 5-minute interval HRV intraday data array */
    /** @var array<mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $hrvIntradayData = null;

    /** Nightly SpO₂ average (%) */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $spo2Avg = null;

    /** Nightly SpO₂ minimum (%) */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $spo2Min = null;

    /** Nightly SpO₂ maximum (%) */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $spo2Max = null;

    /** Average nightly breathing rate (breaths/min) */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $breathingRate = null;

    /** Nightly relative skin temperature delta (°C from personal baseline) */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $skinTemperatureRelative = null;

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

    public function getRmssd(): ?float
    {
        return $this->rmssd;
    }

    public function setRmssd(?float $rmssd): static
    {
        $this->rmssd = $rmssd;

        return $this;
    }

    public function getDeepRmssd(): ?float
    {
        return $this->deepRmssd;
    }

    public function setDeepRmssd(?float $deepRmssd): static
    {
        $this->deepRmssd = $deepRmssd;

        return $this;
    }

    /** @return array<mixed>|null */
    public function getHrvIntradayData(): ?array
    {
        return $this->hrvIntradayData;
    }

    /** @param array<mixed>|null $hrvIntradayData */
    public function setHrvIntradayData(?array $hrvIntradayData): static
    {
        $this->hrvIntradayData = $hrvIntradayData;

        return $this;
    }

    public function getSpo2Avg(): ?float
    {
        return $this->spo2Avg;
    }

    public function setSpo2Avg(?float $spo2Avg): static
    {
        $this->spo2Avg = $spo2Avg;

        return $this;
    }

    public function getSpo2Min(): ?float
    {
        return $this->spo2Min;
    }

    public function setSpo2Min(?float $spo2Min): static
    {
        $this->spo2Min = $spo2Min;

        return $this;
    }

    public function getSpo2Max(): ?float
    {
        return $this->spo2Max;
    }

    public function setSpo2Max(?float $spo2Max): static
    {
        $this->spo2Max = $spo2Max;

        return $this;
    }

    public function getBreathingRate(): ?float
    {
        return $this->breathingRate;
    }

    public function setBreathingRate(?float $breathingRate): static
    {
        $this->breathingRate = $breathingRate;

        return $this;
    }

    public function getSkinTemperatureRelative(): ?float
    {
        return $this->skinTemperatureRelative;
    }

    public function setSkinTemperatureRelative(?float $skinTemperatureRelative): static
    {
        $this->skinTemperatureRelative = $skinTemperatureRelative;

        return $this;
    }
}
