<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BodyMeasurementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: BodyMeasurementRepository::class)]
#[Assert\Callback([self::class, 'validateAtLeastOneField'])]
#[ORM\Table(name: 'body_measurement')]
#[ORM\Index(name: 'idx_body_measurement_athlete_date', columns: ['athlete_id', 'measurement_date'])]
class BodyMeasurement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'athlete_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $athlete;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $measurementDate;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?float $weightKg = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?float $chestCm = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?float $waistCm = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?float $hipsCm = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?float $armsCm = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Validates that at least one measurement field (weight or body) is provided.
     */
    public static function validateAtLeastOneField(self $measurement, ExecutionContextInterface $context): void
    {
        $hasAnyField = null !== $measurement->weightKg
            || null !== $measurement->chestCm
            || null !== $measurement->waistCm
            || null !== $measurement->hipsCm
            || null !== $measurement->armsCm;

        if (!$hasAnyField) {
            $context->buildViolation('Debes proporcionar al menos un valor de medición (peso, pecho, cintura, caderas o brazos).')
                ->atPath('weightKg')
                ->addViolation();
        }
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

    public function getMeasurementDate(): \DateTimeImmutable
    {
        return $this->measurementDate;
    }

    public function setMeasurementDate(\DateTimeImmutable $measurementDate): static
    {
        $this->measurementDate = $measurementDate;

        return $this;
    }

    public function getWeightKg(): ?float
    {
        return $this->weightKg;
    }

    public function setWeightKg(?float $weightKg): static
    {
        $this->weightKg = $weightKg;

        return $this;
    }

    public function getChestCm(): ?float
    {
        return $this->chestCm;
    }

    public function setChestCm(?float $chestCm): static
    {
        $this->chestCm = $chestCm;

        return $this;
    }

    public function getWaistCm(): ?float
    {
        return $this->waistCm;
    }

    public function setWaistCm(?float $waistCm): static
    {
        $this->waistCm = $waistCm;

        return $this;
    }

    public function getHipsCm(): ?float
    {
        return $this->hipsCm;
    }

    public function setHipsCm(?float $hipsCm): static
    {
        $this->hipsCm = $hipsCm;

        return $this;
    }

    public function getArmsCm(): ?float
    {
        return $this->armsCm;
    }

    public function setArmsCm(?float $armsCm): static
    {
        $this->armsCm = $armsCm;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
