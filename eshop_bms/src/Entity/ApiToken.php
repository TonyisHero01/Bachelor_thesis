<?php

namespace App\Entity;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
class ApiToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $publicId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $secretHash;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $scopes = [];

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // ✅ 改成 datetime_immutable（关键）
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ===== Getters / Setters =====

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function setPublicId(string $publicId): self
    {
        $this->publicId = $publicId;
        return $this;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    public function setSecretHash(string $secretHash): self
    {
        $this->secretHash = $secretHash;
        return $this;
    }

    public function getScopes(): ?array
    {
        return $this->scopes;
    }

    public function setScopes(?array $scopes): self
    {
        $this->scopes = $scopes;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function setLastUsedAt(?\DateTimeImmutable $dt): self
    {
        $this->lastUsedAt = $dt;
        return $this;
    }
}