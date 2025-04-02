<?php

namespace App\Entity;

use App\Repository\ColorRepository;
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getHex(): ?string
    {
        return $this->hex;
    }

    public function setHex(string $hex): static
    {
        if (!preg_match('/^#[A-Fa-f0-9]{6}$/', $hex)) {
            throw new \InvalidArgumentException("Invalid hex color format: " . $hex);
        }
        
        $this->hex = $hex;
        return $this;
    }
}