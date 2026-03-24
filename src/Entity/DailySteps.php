<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DailyStepsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyStepsRepository::class)]
#[ORM\Table(name: 'daily_steps')]
#[ORM\UniqueConstraint(name: 'uq_daily_steps_user_date', columns: ['user_id', 'date'])]
class DailySteps
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
    private int $steps = 0;

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

    public function getSteps(): int
    {
        return $this->steps;
    }

    public function setSteps(int $steps): static
    {
        $this->steps = $steps;

        return $this;
    }
}
