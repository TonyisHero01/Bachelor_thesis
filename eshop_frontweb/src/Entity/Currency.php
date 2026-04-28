<?php

namespace App\Entity;

use App\Repository\CurrencyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
class Currency
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 3)]
    private ?string $name = null;

    #[ORM\Column]
    private ?float $value = null;

    #[ORM\Column]
    private ?bool $isDefault = null;

    /**
     * Get the currency ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the currency name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the currency name.
     *
     * @param string $name
     * @return static
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the currency value.
     *
     * @return float|null
     */
    public function getValue(): ?float
    {
        return $this->value;
    }

    /**
     * Set the currency value.
     *
     * @param float $value
     * @return static
     */
    public function setValue(float $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Check if the currency is default.
     *
     * @return bool|null
     */
    public function isIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    /**
     * Set if the currency is default.
     *
     * @param bool $isDefault
     * @return static
     */
    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }
    /**
     * Convert to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
