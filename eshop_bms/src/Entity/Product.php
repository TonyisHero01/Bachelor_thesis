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

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * Convert product to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * Get the product ID.
     *
     * @return int|null
     */
    public function getId(): ?int { return $this->id; }

    /**
     * Get the product name.
     *
     * @return string|null
     */
    public function getName(): ?string { return $this->name; }

    /**
     * Set the product name.
     *
     * @param string $name
     * @return self
     */
    public function setName(string $name): static { $this->name = $name; return $this; }

    /**
     * Get the category.
     *
     * @return Category|null
     */
    public function getCategory(): ?Category { return $this->category; }

    /**
     * Set the category.
     *
     * @param Category|null $category
     * @return self
     */
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }

    /**
     * Get the description.
     *
     * @return string|null
     */
    public function getDescription(): ?string { return $this->description; }

    /**
     * Set the description.
     *
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    /**
     * Get the stock quantity.
     *
     * @return int|null
     */
    public function getNumberInStock(): ?int { return $this->number_in_stock; }

    /**
     * Set the stock quantity.
     *
     * @param int $number_in_stock
     * @return self
     */
    public function setNumberInStock(int $number_in_stock): static { $this->number_in_stock = $number_in_stock; return $this; }

    /**
     * Get image URLs.
     *
     * @return array|null
     */
    public function getImageUrls(): ?array { return $this->image_urls; }

    /**
     * Set image URLs.
     *
     * @param array|null $image_urls
     * @return self
     */
    public function setImageUrls(?array $image_urls): static { $this->image_urls = $image_urls; return $this; }

    /**
     * Get creation timestamp.
     *
     * @return \DateTimeImmutable|null
     */
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    /**
     * Set creation timestamp.
     *
     * @param \DateTimeImmutable $createdAt
     * @return self
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    /**
     * Get update timestamp.
     *
     * @return \DateTimeImmutable|null
     */
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    /**
     * Set update timestamp.
     *
     * @param \DateTimeImmutable $updatedAt
     * @return self
     */
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    /**
     * Get the size.
     *
     * @return Size|null
     */
    public function getSize(): ?Size { return $this->size; }

    /**
     * Set the size.
     *
     * @param Size|null $size
     * @return self
     */
    public function setSize(?Size $size): static { $this->size = $size; return $this; }

    /**
     * Get the width.
     *
     * @return float|null
     */
    public function getWidth(): ?float { return $this->width; }

    /**
     * Set the width.
     *
     * @param float|null $width
     * @return self
     */
    public function setWidth(?float $width): static { $this->width = $width; return $this; }

    /**
     * Get the height.
     *
     * @return float|null
     */
    public function getHeight(): ?float { return $this->height; }

    /**
     * Set the height.
     *
     * @param float|null $height
     * @return self
     */
    public function setHeight(?float $height): static { $this->height = $height; return $this; }

    /**
     * Get the length.
     *
     * @return float|null
     */
    public function getLength(): ?float { return $this->length; }

    /**
     * Set the length.
     *
     * @param float|null $length
     * @return self
     */
    public function setLength(?float $length): static { $this->length = $length; return $this; }

    /**
     * Get the weight.
     *
     * @return float|null
     */
    public function getWeight(): ?float { return $this->weight; }

    /**
     * Set the weight.
     *
     * @param float|null $weight
     * @return self
     */
    public function setWeight(?float $weight): static { $this->weight = $weight; return $this; }

    /**
     * Get the material.
     *
     * @return string|null
     */
    public function getMaterial(): ?string { return $this->material; }

    /**
     * Set the material.
     *
     * @param string|null $material
     * @return self
     */
    public function setMaterial(?string $material): static { $this->material = $material; return $this; }

    /**
     * Get the color.
     *
     * @return Color|null
     */
    public function getColor(): ?Color { return $this->color; }

    /**
     * Set the color.
     *
     * @param Color|null $color
     * @return self
     */
    public function setColor(?Color $color): static { $this->color = $color; return $this; }

    /**
     * Get the price.
     *
     * @return float|null
     */
    public function getPrice(): ?float { return $this->price; }

    /**
     * Set the price.
     *
     * @param float $price
     * @return self
     */
    public function setPrice(float $price): static { $this->price = $price; return $this; }

    /**
     * Get whether the product is hidden.
     *
     * @return bool
     */
    public function getHidden(): bool { return $this->hidden; }

    /**
     * Set whether the product is hidden.
     *
     * @param bool $hidden
     * @return self
     */
    public function setHidden(bool $hidden): static { $this->hidden = $hidden; return $this; }

    /**
     * Get the discount percentage.
     *
     * @return float
     */
    public function getDiscount(): float { return $this->discount; }

    /**
     * Set the discount percentage.
     *
     * @param float $discount
     * @return self
     */
    public function setDiscount(float $discount): static { $this->discount = $discount; return $this; }

    /**
     * Get the currency.
     *
     * @return Currency|null
     */
    public function getCurrency(): ?Currency { return $this->currency; }

    /**
     * Set the currency.
     *
     * @param Currency $currency
     * @return self
     */
    public function setCurrency(Currency $currency): static { $this->currency = $currency; return $this; }

    /**
     * Get custom attributes.
     *
     * @return array|null
     */
    public function getAttributes(): ?array { return $this->attributes; }

    /**
     * Set custom attributes.
     *
     * @param array|null $attributes
     * @return self
     */
    public function setAttributes(?array $attributes): static { $this->attributes = $attributes; return $this; }

    /**
     * Get the product version.
     *
     * @return int
     */
    public function getVersion(): int { return $this->version; }

    /**
     * Set the product version.
     *
     * @param int $version
     * @return self
     */
    public function setVersion(int $version): static { $this->version = $version; return $this; }

    /**
     * Get the SKU.
     *
     * @return string|null
     */
    public function getSku(): ?string { return $this->sku; }

    /**
     * Set the SKU.
     *
     * @param string $sku
     * @return self
     */
    public function setSku(string $sku): static { $this->sku = $sku; return $this; }

    /**
     * Get the tax rate.
     *
     * @return float
     */
    public function getTaxRate(): float { return $this->taxRate; }

    /**
     * Set the tax rate.
     *
     * @param float $taxRate
     * @return self
     */
    public function setTaxRate(float $taxRate): static { $this->taxRate = $taxRate; return $this; }

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
     * @param ProductTranslation $translation
     * @return self
     */
    public function addTranslation(ProductTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setProduct($this);
        }

        return $this;
    }

    /**
     * Remove a translation.
     *
     * @param ProductTranslation $translation
     * @return self
     */
    public function removeTranslation(ProductTranslation $translation): static
    {
        $this->translations->removeElement($translation);
        return $this;
    }

    /**
     * Get translation by locale.
     *
     * @param string|null $locale
     * @return ProductTranslation|null
     */
    public function getTranslation(?string $locale): ?ProductTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Get translated product name.
     *
     * @param string|null $locale
     * @return string
     */
    public function getTranslatedName(?string $locale): string
    {
        return $this->getTranslation($locale)?->getName() ?? $this->name ?? '';
    }

    /**
     * Get translated product description.
     *
     * @param string|null $locale
     * @return string
     */
    public function getTranslatedDescription(?string $locale): string
    {
        return $this->getTranslation($locale)?->getDescription() ?? $this->description ?? '';
    }

    /**
     * Get translated product material.
     *
     * @param string|null $locale
     * @return string
     */
    public function getTranslatedMaterial(?string $locale): string
    {
        return $this->getTranslation($locale)?->getMaterial() ?? $this->material ?? '';
    }

    /**
     * Check if the product has images.
     *
     * @return bool
     */
    public function hasImages(): bool
    {
        return is_array($this->image_urls) && count($this->image_urls) > 0;
    }
}