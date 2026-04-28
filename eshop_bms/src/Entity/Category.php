<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: CategoryTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * Convert category to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * Get the category ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the category name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the category name.
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
     * @param CategoryTranslation $translation
     * @return self
     */
    public function addTranslation(CategoryTranslation $translation): self
    {
        if (!$this->translations->contains($translation)) {
            $this->translations[] = $translation;
            $translation->setCategory($this);
        }

        return $this;
    }

    /**
     * Remove a translation.
     *
     * @param CategoryTranslation $translation
     * @return self
     */
    public function removeTranslation(CategoryTranslation $translation): self
    {
        $this->translations->removeElement($translation);
        return $this;
    }

    /**
     * Get translated category name by locale.
     *
     * @param string $locale
     * @return string|null
     */
    public function getTranslatedName(string $locale): ?string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation->getName();
            }
        }

        return $this->name;
    }
}