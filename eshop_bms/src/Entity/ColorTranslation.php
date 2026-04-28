<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: "color_translation",
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: "uniq_color_locale", columns: ["color_id", "locale"])
    ]
)]
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
     * Get the related color.
     *
     * @return Color
     */
    public function getColor(): Color
    {
        return $this->color;
    }

    /**
     * Set the related color.
     *
     * @param Color $color
     * @return self
     */
    public function setColor(Color $color): self
    {
        $this->color = $color;
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
     * Get the translated color name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the translated color name.
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