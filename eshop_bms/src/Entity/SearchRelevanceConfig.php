<?php

namespace App\Entity;

use App\Repository\SearchRelevanceConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SearchRelevanceConfigRepository::class)]
class SearchRelevanceConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = 'Default relevance configuration';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 20])]
    private int $nameWeight = 20;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 5])]
    private int $descriptionWeight = 5;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 4])]
    private int $categoryWeight = 4;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 2])]
    private int $materialWeight = 2;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 2])]
    private int $colorWeight = 2;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 2])]
    private int $sizeWeight = 2;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 2])]
    private int $attributesWeight = 2;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.35])]
    private float $sameCategoryBonus = 0.35;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.15])]
    private float $sameMaterialBonus = 0.15;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.10])]
    private float $sameColorBonus = 0.10;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.10])]
    private float $sameSizeBonus = 0.10;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $active = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'float', options: ['default' => 0.35])]
    private float $sameCategoryRecommendationWeight = 0.35;

    #[ORM\Column(type: 'float', options: ['default' => 0.10])]
    private float $sameColorRecommendationWeight = 0.10;

    #[ORM\Column(type: 'float', options: ['default' => 0.10])]
    private float $sameSizeRecommendationWeight = 0.10;

    #[ORM\Column(type: 'float', options: ['default' => 0.30])]
    private float $wishlistRecommendationWeight = 0.30;

    #[ORM\Column(type: 'float', options: ['default' => 0.25])]
    private float $orderHistoryRecommendationWeight = 0.25;

    #[ORM\Column(type: 'float', options: ['default' => 0.20])]
    private float $searchHistoryRecommendationWeight = 0.20;

    #[ORM\Column(type: 'float', options: ['default' => 0.35])]
    private float $viewHistoryRecommendationWeight = 0.35;

    #[ORM\Column(type: 'integer', options: ['default' => 4])]
    private int $maxRecommendationPerCategory = 4;

    #[ORM\Column(type: 'float', options: ['default' => 0.10])]
    private float $recommendationDiversityPenalty = 0.10;

    #[ORM\Column(type: 'string', length: 30, options: ['default' => 'lexical'])]
    private string $searchMethod = 'lexical';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $recommendationEnabled = true;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $recommendationLoggingEnabled = true;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getNameWeight(): int { return $this->nameWeight; }
    public function setNameWeight(int $nameWeight): static { $this->nameWeight = $nameWeight; return $this; }

    public function getDescriptionWeight(): int { return $this->descriptionWeight; }
    public function setDescriptionWeight(int $descriptionWeight): static { $this->descriptionWeight = $descriptionWeight; return $this; }

    public function getCategoryWeight(): int { return $this->categoryWeight; }
    public function setCategoryWeight(int $categoryWeight): static { $this->categoryWeight = $categoryWeight; return $this; }

    public function getMaterialWeight(): int { return $this->materialWeight; }
    public function setMaterialWeight(int $materialWeight): static { $this->materialWeight = $materialWeight; return $this; }

    public function getColorWeight(): int { return $this->colorWeight; }
    public function setColorWeight(int $colorWeight): static { $this->colorWeight = $colorWeight; return $this; }

    public function getSizeWeight(): int { return $this->sizeWeight; }
    public function setSizeWeight(int $sizeWeight): static { $this->sizeWeight = $sizeWeight; return $this; }

    public function getAttributesWeight(): int { return $this->attributesWeight; }
    public function setAttributesWeight(int $attributesWeight): static { $this->attributesWeight = $attributesWeight; return $this; }

    public function getSameCategoryBonus(): float { return $this->sameCategoryBonus; }
    public function setSameCategoryBonus(float $sameCategoryBonus): static { $this->sameCategoryBonus = $sameCategoryBonus; return $this; }

    public function getSameMaterialBonus(): float { return $this->sameMaterialBonus; }
    public function setSameMaterialBonus(float $sameMaterialBonus): static { $this->sameMaterialBonus = $sameMaterialBonus; return $this; }

    public function getSameColorBonus(): float { return $this->sameColorBonus; }
    public function setSameColorBonus(float $sameColorBonus): static { $this->sameColorBonus = $sameColorBonus; return $this; }

    public function getSameSizeBonus(): float { return $this->sameSizeBonus; }
    public function setSameSizeBonus(float $sameSizeBonus): static { $this->sameSizeBonus = $sameSizeBonus; return $this; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSameCategoryRecommendationWeight(): float
    {
        return $this->sameCategoryRecommendationWeight;
    }

    public function setSameCategoryRecommendationWeight(float $weight): static
    {
        $this->sameCategoryRecommendationWeight = $weight;
        return $this;
    }

    public function getSameColorRecommendationWeight(): float
    {
        return $this->sameColorRecommendationWeight;
    }

    public function setSameColorRecommendationWeight(float $weight): static
    {
        $this->sameColorRecommendationWeight = $weight;
        return $this;
    }

    public function getSameSizeRecommendationWeight(): float
    {
        return $this->sameSizeRecommendationWeight;
    }

    public function setSameSizeRecommendationWeight(float $weight): static
    {
        $this->sameSizeRecommendationWeight = $weight;
        return $this;
    }

    public function getWishlistRecommendationWeight(): float
    {
        return $this->wishlistRecommendationWeight;
    }

    public function setWishlistRecommendationWeight(float $weight): static
    {
        $this->wishlistRecommendationWeight = $weight;
        return $this;
    }

    public function getOrderHistoryRecommendationWeight(): float
    {
        return $this->orderHistoryRecommendationWeight;
    }

    public function setOrderHistoryRecommendationWeight(float $weight): static
    {
        $this->orderHistoryRecommendationWeight = $weight;
        return $this;
    }

    public function getSearchHistoryRecommendationWeight(): float
    {
        return $this->searchHistoryRecommendationWeight;
    }

    public function setSearchHistoryRecommendationWeight(float $weight): static
    {
        $this->searchHistoryRecommendationWeight = $weight;
        return $this;
    }

    public function getViewHistoryRecommendationWeight(): float
    {
        return $this->viewHistoryRecommendationWeight;
    }

    public function setViewHistoryRecommendationWeight(float $weight): static
    {
        $this->viewHistoryRecommendationWeight = $weight;

        return $this;
    }

    public function getMaxRecommendationPerCategory(): int
    {
        return $this->maxRecommendationPerCategory;
    }

    public function setMaxRecommendationPerCategory(int $value): static
    {
        $this->maxRecommendationPerCategory = $value;

        return $this;
    }

    public function getRecommendationDiversityPenalty(): float
    {
        return $this->recommendationDiversityPenalty;
    }

    public function setRecommendationDiversityPenalty(float $value): static
    {
        $this->recommendationDiversityPenalty = $value;

        return $this;
    }

    public function getSearchMethod(): string
    {
        return $this->searchMethod;
    }

    public function setSearchMethod(string $searchMethod): static
    {
        $allowed = [
            'lexical',
            'semantic_vector',
            'elasticsearch_bm25',
        ];

        if (!in_array($searchMethod, $allowed, true)) {
            $searchMethod = 'lexical';
        }

        $this->searchMethod = $searchMethod;

        return $this;
    }

    public function isRecommendationEnabled(): bool
    {
        return $this->recommendationEnabled;
    }

    public function setRecommendationEnabled(bool $recommendationEnabled): self
    {
        $this->recommendationEnabled = $recommendationEnabled;

        return $this;
    }

    public function isRecommendationLoggingEnabled(): bool
    {
        return $this->recommendationLoggingEnabled;
    }

    public function setRecommendationLoggingEnabled(bool $recommendationLoggingEnabled): self
    {
        $this->recommendationLoggingEnabled = $recommendationLoggingEnabled;

        return $this;
    }
}