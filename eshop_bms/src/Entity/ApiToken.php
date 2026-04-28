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

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * Get the token ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the public identifier.
     *
     * @return string
     */
    public function getPublicId(): string
    {
        return $this->publicId;
    }

    /**
     * Set the public identifier.
     *
     * @param string $publicId
     * @return self
     */
    public function setPublicId(string $publicId): self
    {
        $this->publicId = $publicId;
        return $this;
    }

    /**
     * Get the hashed secret.
     *
     * @return string
     */
    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    /**
     * Set the hashed secret.
     *
     * @param string $secretHash
     * @return self
     */
    public function setSecretHash(string $secretHash): self
    {
        $this->secretHash = $secretHash;
        return $this;
    }

    /**
     * Get the label.
     *
     * @return string|null
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * Set the label.
     *
     * @param string|null $label
     * @return self
     */
    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Get token scopes.
     *
     * @return array|null
     */
    public function getScopes(): ?array
    {
        return $this->scopes;
    }

    /**
     * Set token scopes.
     *
     * @param array|null $scopes
     * @return self
     */
    public function setScopes(?array $scopes): self
    {
        $this->scopes = $scopes;
        return $this;
    }

    /**
     * Check if the token is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Set whether the token is active.
     *
     * @param bool $active
     * @return self
     */
    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    /**
     * Get the creation timestamp.
     *
     * @return \DateTimeImmutable
     */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set the creation timestamp.
     *
     * @param \DateTimeImmutable $createdAt
     * @return self
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get the last used timestamp.
     *
     * @return \DateTimeImmutable|null
     */
    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    /**
     * Set the last used timestamp.
     *
     * @param \DateTimeImmutable|null $lastUsedAt
     * @return self
     */
    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }
}