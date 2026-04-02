<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DailyWaterIntakeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyWaterIntakeRepository::class)]
#[ORM\Table(name: 'daily_water_intake')]
#[ORM\UniqueConstraint(name: 'uq_daily_water_intake_user_date', columns: ['user_id', 'date'])]
class DailyWaterIntake
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

    #[ORM\Column(options: ['default' => 0])]
    private int $amountMl = 0;

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

    public function getAmountMl(): int
    {
        return $this->amountMl;
    }

    public function setAmountMl(int $amountMl): static
    {
        $this->amountMl = $amountMl;

        return $this;
    }
}
