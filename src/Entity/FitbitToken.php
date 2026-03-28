<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FitbitTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FitbitTokenRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'fitbit_token')]
class FitbitToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'fitbitToken', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /** Stored encrypted, decrypted in memory */
    #[ORM\Column(type: 'text')]
    private string $accessToken = '';

    #[ORM\Column(type: 'text')]
    private string $refreshToken = '';

    /** Raw (unencrypted) values — not persisted, managed by lifecycle callbacks */
    private ?string $plainAccessToken = null;

    private ?string $plainRefreshToken = null;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'json')]
    private array $grantedScopes = [];

    #[ORM\Column]
    private bool $isValid = true;

    #[ORM\Column]
    private \DateTimeImmutable $connectedAt;

    public function __construct()
    {
        $this->expiresAt = new \DateTimeImmutable();
        $this->connectedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPlainAccessToken(): ?string
    {
        return $this->plainAccessToken;
    }

    public function setPlainAccessToken(string $token): static
    {
        $this->plainAccessToken = $token;

        return $this;
    }

    public function getPlainRefreshToken(): ?string
    {
        return $this->plainRefreshToken;
    }

    public function setPlainRefreshToken(string $token): static
    {
        $this->plainRefreshToken = $token;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getGrantedScopes(): array
    {
        return $this->grantedScopes;
    }

    public function setGrantedScopes(array $grantedScopes): static
    {
        $this->grantedScopes = $grantedScopes;

        return $this;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function setIsValid(bool $isValid): static
    {
        $this->isValid = $isValid;

        return $this;
    }

    public function getConnectedAt(): \DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function setConnectedAt(\DateTimeImmutable $connectedAt): static
    {
        $this->connectedAt = $connectedAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isExpiringSoon(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable('+60 seconds');
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function encryptTokens(): void
    {
        $key = $this->getEncryptionKey();
        if ($this->plainAccessToken !== null) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $this->accessToken = base64_encode($nonce.sodium_crypto_secretbox($this->plainAccessToken, $nonce, $key));
        }
        if ($this->plainRefreshToken !== null) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $this->refreshToken = base64_encode($nonce.sodium_crypto_secretbox($this->plainRefreshToken, $nonce, $key));
        }
        sodium_memzero($key);
    }

    #[ORM\PostLoad]
    public function decryptTokens(): void
    {
        $key = $this->getEncryptionKey();
        if ($this->accessToken) {
            $decoded = base64_decode($this->accessToken);
            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $this->plainAccessToken = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        }
        if ($this->refreshToken) {
            $decoded = base64_decode($this->refreshToken);
            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $this->plainRefreshToken = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        }
        sodium_memzero($key);
    }

    private function getEncryptionKey(): string
    {
        $keyBase64 = $_ENV['FITBIT_ENCRYPTION_KEY'] ?? '';
        if (!$keyBase64) {
            throw new \RuntimeException('FITBIT_ENCRYPTION_KEY env var is not set');
        }
        $key = base64_decode($keyBase64);
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('FITBIT_ENCRYPTION_KEY must be 32 bytes base64-encoded');
        }

        return $key;
    }
}
