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

    public function getId(): int
    {
        return $this->id;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}