<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final class ApiTokenUser implements UserInterface
{
    public function __construct(
        private string $identifier,
        private array $roles = []
    ) {
    }

    /**
     * Returns the unique identifier for this API token user.
     *
     * This value corresponds to the token publicId.
     */
    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Returns the roles granted to this API token user.
     *
     * If no roles are defined, ROLE_API is used as a fallback.
     *
     * @return string[]
     */
    public function getRoles(): array
    {
        return array_unique($this->roles ?: ['ROLE_API']);
    }

    /**
     * Removes sensitive data from the user.
     *
     * Not applicable for API token authentication.
     */
    public function eraseCredentials(): void
    {
    }

    /**
     * Returns the password for the user.
     *
     * API token users do not use password-based authentication.
     */
    public function getPassword(): ?string
    {
        return null;
    }

    /**
     * Returns the salt used to hash the password.
     *
     * Not applicable for API token authentication.
     */
    public function getSalt(): ?string
    {
        return null;
    }
}