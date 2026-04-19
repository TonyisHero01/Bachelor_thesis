<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: "category_id", referencedColumnName: "id", nullable: true)]
    private ?Category $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $number_in_stock = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $image_urls = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Size::class)]
    #[ORM\JoinColumn(name: "size_id", referencedColumnName: "id", nullable: true)]
    private ?Size $size = null;

    #[ORM\Column(nullable: true)]
    private ?float $width = null;

    #[ORM\Column(nullable: true)]
    private ?float $height = null;

    #[ORM\Column(nullable: true)]
    private ?float $length = null;

    #[ORM\Column(nullable: true)]
    private ?float $weight = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $material = null;

    #[ORM\ManyToOne(targetEntity: Color::class)]
    #[ORM\JoinColumn(name: "color_id", referencedColumnName: "id", nullable: true)]
    private ?Color $color = null;

    #[ORM\Column]
    private ?float $price = null;

    #[ORM\Column(type: 'boolean')]
    private bool $hidden = false;

    #[ORM\Column]
    private float $discount = 100.0;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: 'currency_id', referencedColumnName: 'id', nullable: false)]
    private ?Currency $currency = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $attributes = [];

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(type: 'string', length: 255, nullable: false, options: ['default' => 'UNKNOWN'])]
    private ?string $sku = 'UNKNOWN';

    #[ORM\Column(type: 'float')]
    private float $taxRate = 21.0;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getNumberInStock(): ?int { return $this->number_in_stock; }
    public function setNumberInStock(int $number_in_stock): static { $this->number_in_stock = $number_in_stock; return $this; }

    public function getImageUrls(): ?array { return $this->image_urls; }
    public function setImageUrls(?array $image_urls): static { $this->image_urls = $image_urls; return $this; }

    public function getSize(): ?Size { return $this->size; }
    public function setSize(?Size $size): static { $this->size = $size; return $this; }

    public function getWidth(): ?float { return $this->width; }
    public function setWidth(?float $width): static { $this->width = $width; return $this; }

    public function getHeight(): ?float { return $this->height; }
    public function setHeight(?float $height): static { $this->height = $height; return $this; }

    public function getLength(): ?float { return $this->length; }
    public function setLength(?float $length): static { $this->length = $length; return $this; }

    public function getWeight(): ?float { return $this->weight; }
    public function setWeight(?float $weight): static { $this->weight = $weight; return $this; }

    public function getMaterial(): ?string { return $this->material; }
    public function setMaterial(?string $material): static { $this->material = $material; return $this; }

    public function getColor(): ?Color { return $this->color; }
    public function setColor(?Color $color): static { $this->color = $color; return $this; }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(float $price): static { $this->price = $price; return $this; }

    public function getHidden(): bool { return $this->hidden; }
    public function setHidden(bool $hidden): static { $this->hidden = $hidden; return $this; }

    public function getDiscount(): float { return $this->discount; }
    public function setDiscount(float $discount): static { $this->discount = $discount; return $this; }

    public function getCurrency(): ?Currency { return $this->currency; }
    public function setCurrency(Currency $currency): static { $this->currency = $currency; return $this; }

    public function getAttributes(): ?array { return $this->attributes; }
    public function setAttributes(?array $attributes): static { $this->attributes = $attributes; return $this; }

    public function getVersion(): int { return $this->version; }
    public function setVersion(int $version): static { $this->version = $version; return $this; }

    public function getSku(): ?string { return $this->sku; }
    public function setSku(string $sku): static { $this->sku = $sku; return $this; }

    public function getTaxRate(): float { return $this->taxRate; }
    public function setTaxRate(float $taxRate): static { $this->taxRate = $taxRate; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(ProductTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setProduct($this);
        }
        return $this;
    }

    public function removeTranslation(ProductTranslation $translation): static
    {
        $this->translations->removeElement($translation);
        return $this;
    }

    public function getTranslation(?string $locale): ?ProductTranslation
    {
        foreach ($this->translations as $t) {
            if ($t->getLocale() === $locale) {
                return $t;
            }
        }
        return null;
    }

    public function getTranslatedName(?string $locale): string
    {
        return $this->getTranslation($locale)?->getName() ?? $this->name ?? '';
    }

    public function getTranslatedDescription(?string $locale): string
    {
        return $this->getTranslation($locale)?->getDescription() ?? $this->description ?? '';
    }

    public function getTranslatedMaterial(?string $locale): string
    {
        return $this->getTranslation($locale)?->getMaterial() ?? $this->material ?? '';
    }
    public function hasImages(): bool
    {
        return is_array($this->image_urls) && count($this->image_urls) > 0;
    }
}