<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '"user"')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email = '';

    /** @var array<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password = '';

    #[ORM\Column(type: 'string', length: 100)]
    private string $firstName = '';

    #[ORM\Column(type: 'string', length: 100)]
    private string $lastName = '';

    /**
     * @var Collection<int, CoachAthlete>
     */
    #[ORM\OneToMany(targetEntity: CoachAthlete::class, mappedBy: 'coach', cascade: ['persist', 'remove'])]
    private Collection $coachRelations;

    /**
     * @var Collection<int, CoachAthlete>
     */
    #[ORM\OneToMany(targetEntity: CoachAthlete::class, mappedBy: 'athlete', cascade: ['persist', 'remove'])]
    private Collection $athleteRelations;

    #[ORM\Column(nullable: true)]
    private ?string $profilePictureFilename = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?FitbitToken $fitbitToken = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $heightCm = null;

    public function __construct()
    {
        $this->coachRelations = new ArrayCollection();
        $this->athleteRelations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /** @param array<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getProfilePictureFilename(): ?string
    {
        return $this->profilePictureFilename;
    }

    public function setProfilePictureFilename(?string $filename): static
    {
        $this->profilePictureFilename = $filename;

        return $this;
    }

    /**
     * @return Collection<int, CoachAthlete>
     */
    public function getCoachRelations(): Collection
    {
        return $this->coachRelations;
    }

    /**
     * @return Collection<int, CoachAthlete>
     */
    public function getAthleteRelations(): Collection
    {
        return $this->athleteRelations;
    }

    public function isCoach(): bool
    {
        return in_array('ROLE_ENTRENADOR', $this->getRoles(), true)
            || in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function isAthlete(): bool
    {
        return in_array('ROLE_ATLETA', $this->getRoles(), true);
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFitbitToken(): ?FitbitToken
    {
        return $this->fitbitToken;
    }

    public function setFitbitToken(?FitbitToken $fitbitToken): static
    {
        // set/unset the owning side of the relation if necessary
        if ($fitbitToken === null && $this->fitbitToken !== null) {
            $this->fitbitToken->setUser(null);
        }

        if ($fitbitToken !== null && $fitbitToken->getUser() !== $this) {
            $fitbitToken->setUser($this);
        }

        $this->fitbitToken = $fitbitToken;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getAge(): ?int
    {
        if ($this->birthDate === null) {
            return null;
        }

        return (int) $this->birthDate->diff(new \DateTimeImmutable('today'))->y;
    }

    public function getHeightCm(): ?float
    {
        return $this->heightCm;
    }

    public function setHeightCm(?float $heightCm): static
    {
        $this->heightCm = $heightCm;

        return $this;
    }

    public function hasFitbitConnected(): bool
    {
        return $this->fitbitToken !== null && $this->fitbitToken->isValid();
    }
}
