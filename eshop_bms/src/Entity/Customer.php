<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password_hash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?bool $is_verified = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reset_token = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $resetTokenExpiration = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $wishlist = [];

    /**
     * Get the reset token expiration time.
     *
     * @return \DateTimeInterface|null
     */
    public function getResetTokenExpiration(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiration;
    }

    /**
     * Set the reset token expiration time.
     *
     * @param \DateTimeInterface|null $resetTokenExpiration
     * @return self
     */
    public function setResetTokenExpiration(?\DateTimeInterface $resetTokenExpiration): self
    {
        $this->resetTokenExpiration = $resetTokenExpiration;
        return $this;
    }

    /**
     * Get the customer ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the email address.
     *
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Set the email address.
     *
     * @param string|null $email
     * @return self
     */
    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * Get the password hash.
     *
     * @return string|null
     */
    public function getPasswordHash(): ?string
    {
        return $this->password_hash;
    }

    /**
     * Set the password hash.
     *
     * @param string|null $password_hash
     * @return self
     */
    public function setPasswordHash(?string $password_hash): static
    {
        $this->password_hash = $password_hash;
        return $this;
    }

    /**
     * Get the creation timestamp.
     *
     * @return \DateTimeImmutable|null
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    /**
     * Set the creation timestamp.
     *
     * @param \DateTimeImmutable|null $created_at
     * @return self
     */
    public function setCreatedAt(?\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    /**
     * Check if the customer is verified.
     *
     * @return bool|null
     */
    public function isIsVerified(): ?bool
    {
        return $this->is_verified;
    }

    /**
     * Set whether the customer is verified.
     *
     * @param bool $is_verified
     * @return self
     */
    public function setIsVerified(bool $is_verified): static
    {
        $this->is_verified = $is_verified;
        return $this;
    }

    /**
     * Get the reset token.
     *
     * @return string|null
     */
    public function getResetToken(): ?string
    {
        return $this->reset_token;
    }

    /**
     * Set the reset token.
     *
     * @param string|null $reset_token
     * @return self
     */
    public function setResetToken(?string $reset_token): static
    {
        $this->reset_token = $reset_token;
        return $this;
    }

    /**
     * Get wishlist product IDs.
     *
     * @return array
     */
    public function getWishlist(): array
    {
        return $this->wishlist ?? [];
    }

    /**
     * Set wishlist product IDs.
     *
     * @param array $wishlist
     * @return self
     */
    public function setWishlist(array $wishlist): self
    {
        $this->wishlist = $wishlist;
        return $this;
    }

    /**
     * Add a product to wishlist.
     *
     * @param int $productId
     * @return self
     */
    public function addToWishlist(int $productId): self
    {
        if ($this->wishlist === null) {
            $this->wishlist = [];
        }

        if (!in_array($productId, $this->wishlist, true)) {
            $this->wishlist[] = $productId;
        }

        return $this;
    }

    /**
     * Remove a product from wishlist.
     *
     * @param int $productId
     * @return self
     */
    public function removeFromWishlist(int $productId): self
    {
        $this->wishlist = array_filter($this->wishlist, fn($id) => $id !== $productId);
        return $this;
    }

    /**
     * Get the password (required by Symfony).
     *
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password_hash;
    }

    /**
     * Get user roles.
     *
     * @return array
     */
    public function getRoles(): array
    {
        return ['ROLE_CUSTOMER'];
    }

    /**
     * Get user identifier (email).
     *
     * @return string
     */
    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    /**
     * Erase sensitive credentials.
     */
    public function eraseCredentials(): void
    {
        // No temporary sensitive data stored
    }
}