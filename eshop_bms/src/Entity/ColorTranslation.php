<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "color_translation")]
#[ORM\UniqueConstraint(columns: ["color_id", "locale"])]
class ColorTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Color::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Color $color;

    #[ORM\Column(type: "string", length: 10)]
    private string $locale;

    #[ORM\Column(type: "string", length: 255)]
    private string $name;

    public function getId(): int
    {
        return $this->id;
    }

    public function getColor(): Color
    {
        return $this->color;
    }

    public function setColor(Color $color): self
    {
        $this->color = $color;
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