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
     * Get the currency code (e.g. CZK, EUR).
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the currency code.
     *
     * @param string $name
     * @return self
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the currency value (exchange rate).
     *
     * @return float|null
     */
    public function getValue(): ?float
    {
        return $this->value;
    }

    /**
     * Set the currency value (exchange rate).
     *
     * @param float $value
     * @return self
     */
    public function setValue(float $value): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Check if this is the default currency.
     *
     * @return bool|null
     */
    public function isIsDefault(): ?bool
    {
        return $this->isDefault;
    }

    /**
     * Set whether this is the default currency.
     *
     * @param bool $isDefault
     * @return self
     */
    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    /**
     * Convert currency to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }
}