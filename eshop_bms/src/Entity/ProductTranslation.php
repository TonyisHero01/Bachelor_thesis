<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ProductTranslationRepository;

#[ORM\Entity(repositoryClass: ProductTranslationRepository::class)]
#[ORM\Table(name: 'product_translation', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_product_translation_locale', columns: ['product_id', 'locale'])
])]
class ProductTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: "translations")]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(type: 'string', length: 10)]
    private string $locale;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $material = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $attributes = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->attributes = [];
    }

    /**
     * Get the translation ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the related product.
     *
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        return $this->product;
    }

    /**
     * Set the related product.
     *
     * @param Product|null $product
     * @return self
     */
    public function setProduct(?Product $product): self
    {
        $this->product = $product;
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
     * Get the translated product name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the translated product name.
     *
     * @param string|null $name
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the translated description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the translated description.
     *
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the translated material.
     *
     * @return string|null
     */
    public function getMaterial(): ?string
    {
        return $this->material;
    }

    /**
     * Set the translated material.
     *
     * @param string|null $material
     * @return self
     */
    public function setMaterial(?string $material): self
    {
        $this->material = $material;
        return $this;
    }

    /**
     * Get additional attributes.
     *
     * @return array|null
     */
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * Set additional attributes.
     *
     * @param array|null $attributes
     * @return self
     */
    public function setAttributes(?array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }
}