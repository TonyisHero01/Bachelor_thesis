<?php

namespace App\Entity;

use App\Repository\ColorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ColorRepository::class)]
#[ORM\Table(name: "ProductColor")]
class Color
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 7, nullable: false, options: ["default" => "#FFFFFF"])]
    private ?string $hex = '#FFFFFF';

    #[ORM\OneToMany(mappedBy: 'color', targetEntity: ColorTranslation::class, cascade: ['persist', 'remove'])]
    private Collection $translations;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * Convert color to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * Get the color ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the color name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the color name.
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
     * Get the hex color code.
     *
     * @return string|null
     */
    public function getHex(): ?string
    {
        return $this->hex;
    }

    /**
     * Set the hex color code.
     *
     * @param string $hex
     * @return self
     */
    public function setHex(string $hex): static
    {
        if (!preg_match('/^#[A-Fa-f0-9]{6}$/', $hex)) {
            throw new \InvalidArgumentException("Invalid hex color format: " . $hex);
        }

        $this->hex = $hex;
        return $this;
    }

    /**
     * Get translations collection.
     *
     * @return Collection
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    /**
     * Add a translation.
     *
     * @param ColorTranslation $translation
     * @return self
     */
    public function addTranslation(ColorTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations[] = $translation;
            $translation->setColor($this);
        }

        return $this;
    }

    /**
     * Remove a translation.
     *
     * @param ColorTranslation $translation
     * @return self
     */
    public function removeTranslation(ColorTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getColor() === $this) {
                $translation->setColor(null);
            }
        }

        return $this;
    }

    /**
     * Get translated color name by locale.
     *
     * @param string $locale
     * @return string
     */
    public function getTranslatedName(string $locale): string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation->getName();
            }
        }

        return $this->name ?? '';
    }
}