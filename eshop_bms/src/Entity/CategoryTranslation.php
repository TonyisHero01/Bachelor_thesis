<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: "category_translation",
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: "uniq_category_locale", columns: ["category_id", "locale"])
    ]
)]
class CategoryTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: "translations")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Category $category;

    #[ORM\Column(type: "string", length: 10)]
    private string $locale;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    /**
     * Get the translation ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the category.
     *
     * @return Category
     */
    public function getCategory(): Category
    {
        return $this->category;
    }

    /**
     * Set the category.
     *
     * @param Category $category
     * @return self
     */
    public function setCategory(Category $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Get the locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set the locale.
     *
     * @param string $locale
     * @return self
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Get the translated category name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the translated category name.
     *
     * @param string $name
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}