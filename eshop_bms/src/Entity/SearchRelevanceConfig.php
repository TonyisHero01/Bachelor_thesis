<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SearchRelevanceConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SearchRelevanceConfigRepository::class)]
#[ORM\Table(name: 'search_relevance_config')]
#[ORM\HasLifecycleCallbacks]
class SearchRelevanceConfig
{
    public const METHOD_LEXICAL = 'lexical';
    public const METHOD_SEMANTIC_VECTOR = 'semantic_vector';
    public const METHOD_ELASTICSEARCH_BM25 = 'elasticsearch_bm25';

    public const SUPPORTED_METHODS = [
        self::METHOD_LEXICAL,
        self::METHOD_SEMANTIC_VECTOR,
        self::METHOD_ELASTICSEARCH_BM25,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name = 'Default relevance configuration';

    /**
     * Each search method has one independent configuration row.
     */
    #[ORM\Column(
        type: Types::STRING,
        length: 30,
        unique: true
    )]
    private string $searchMethod = self::METHOD_LEXICAL;

    /**
     * Determines which method is currently used by Frontweb.
     *
     * The partial unique index that allows at most one active row
     * is defined in the Doctrine migration.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $active = false;

    /*
     * Search field weights
     */

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

    /*
     * Product attribute bonuses
     */

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.35])]
    private float $sameCategoryBonus = 0.35;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.15])]
    private float $sameMaterialBonus = 0.15;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.10])]
    private float $sameColorBonus = 0.10;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.10])]
    private float $sameSizeBonus = 0.10;

    /*
     * Recommendation weights
     */

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.35])]
    private float $sameCategoryRecommendationWeight = 0.35;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.10])]
    private float $sameColorRecommendationWeight = 0.10;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.10])]
    private float $sameSizeRecommendationWeight = 0.10;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.30])]
    private float $wishlistRecommendationWeight = 0.30;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.25])]
    private float $orderHistoryRecommendationWeight = 0.25;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.20])]
    private float $searchHistoryRecommendationWeight = 0.20;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.35])]
    private float $viewHistoryRecommendationWeight = 0.35;

    /*
     * Recommendation diversity
     */

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 4])]
    private int $maxRecommendationPerCategory = 4;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0.10])]
    private float $recommendationDiversityPenalty = 0.10;

    /*
     * Recommendation switches
     */

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $recommendationEnabled = true;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $recommendationLoggingEnabled = true;

    /*
     * Method-specific settings
     *
     * Examples:
     * - Lexical HashingVectorizer parameters
     * - Semantic reranking and candidate pool settings
     * - Elasticsearch query and recommendation parameters
     */
    #[ORM\Column(
        type: Types::JSON,
        options: [
            'jsonb' => true,
            'default' => '{}',
        ]
    )]
    private array $algorithmSettings = [];

    /*
     * Timestamps
     */

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /*
     * Name
     */

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException(
                'Configuration name cannot be empty.'
            );
        }

        $this->name = $name;

        return $this;
    }

    /*
     * Search method
     */

    public function getSearchMethod(): string
    {
        return $this->searchMethod;
    }

    public function setSearchMethod(string $searchMethod): static
    {
        $searchMethod = trim($searchMethod);

        if (!in_array($searchMethod, self::SUPPORTED_METHODS, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unsupported search method "%s". Supported methods: %s.',
                    $searchMethod,
                    implode(', ', self::SUPPORTED_METHODS)
                )
            );
        }

        $this->searchMethod = $searchMethod;

        return $this;
    }

    public static function getSupportedMethods(): array
    {
        return self::SUPPORTED_METHODS;
    }

    /*
     * Active state
     */

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /*
     * Search field weights
     */

    public function getNameWeight(): int
    {
        return $this->nameWeight;
    }

    public function setNameWeight(int $nameWeight): static
    {
        $this->nameWeight = $this->validateIntegerWeight(
            $nameWeight,
            'Name weight'
        );

        return $this;
    }

    public function getDescriptionWeight(): int
    {
        return $this->descriptionWeight;
    }

    public function setDescriptionWeight(int $descriptionWeight): static
    {
        $this->descriptionWeight = $this->validateIntegerWeight(
            $descriptionWeight,
            'Description weight'
        );

        return $this;
    }

    public function getCategoryWeight(): int
    {
        return $this->categoryWeight;
    }

    public function setCategoryWeight(int $categoryWeight): static
    {
        $this->categoryWeight = $this->validateIntegerWeight(
            $categoryWeight,
            'Category weight'
        );

        return $this;
    }

    public function getMaterialWeight(): int
    {
        return $this->materialWeight;
    }

    public function setMaterialWeight(int $materialWeight): static
    {
        $this->materialWeight = $this->validateIntegerWeight(
            $materialWeight,
            'Material weight'
        );

        return $this;
    }

    public function getColorWeight(): int
    {
        return $this->colorWeight;
    }

    public function setColorWeight(int $colorWeight): static
    {
        $this->colorWeight = $this->validateIntegerWeight(
            $colorWeight,
            'Color weight'
        );

        return $this;
    }

    public function getSizeWeight(): int
    {
        return $this->sizeWeight;
    }

    public function setSizeWeight(int $sizeWeight): static
    {
        $this->sizeWeight = $this->validateIntegerWeight(
            $sizeWeight,
            'Size weight'
        );

        return $this;
    }

    public function getAttributesWeight(): int
    {
        return $this->attributesWeight;
    }

    public function setAttributesWeight(int $attributesWeight): static
    {
        $this->attributesWeight = $this->validateIntegerWeight(
            $attributesWeight,
            'Attributes weight'
        );

        return $this;
    }

    /*
     * Product attribute bonuses
     */

    public function getSameCategoryBonus(): float
    {
        return $this->sameCategoryBonus;
    }

    public function setSameCategoryBonus(float $sameCategoryBonus): static
    {
        $this->sameCategoryBonus = $this->validateFloatWeight(
            $sameCategoryBonus,
            'Same category bonus'
        );

        return $this;
    }

    public function getSameMaterialBonus(): float
    {
        return $this->sameMaterialBonus;
    }

    public function setSameMaterialBonus(float $sameMaterialBonus): static
    {
        $this->sameMaterialBonus = $this->validateFloatWeight(
            $sameMaterialBonus,
            'Same material bonus'
        );

        return $this;
    }

    public function getSameColorBonus(): float
    {
        return $this->sameColorBonus;
    }

    public function setSameColorBonus(float $sameColorBonus): static
    {
        $this->sameColorBonus = $this->validateFloatWeight(
            $sameColorBonus,
            'Same color bonus'
        );

        return $this;
    }

    public function getSameSizeBonus(): float
    {
        return $this->sameSizeBonus;
    }

    public function setSameSizeBonus(float $sameSizeBonus): static
    {
        $this->sameSizeBonus = $this->validateFloatWeight(
            $sameSizeBonus,
            'Same size bonus'
        );

        return $this;
    }

    /*
     * Recommendation weights
     */

    public function getSameCategoryRecommendationWeight(): float
    {
        return $this->sameCategoryRecommendationWeight;
    }

    public function setSameCategoryRecommendationWeight(
        float $weight
    ): static {
        $this->sameCategoryRecommendationWeight =
            $this->validateFloatWeight(
                $weight,
                'Same category recommendation weight'
            );

        return $this;
    }

    public function getSameColorRecommendationWeight(): float
    {
        return $this->sameColorRecommendationWeight;
    }

    public function setSameColorRecommendationWeight(
        float $weight
    ): static {
        $this->sameColorRecommendationWeight =
            $this->validateFloatWeight(
                $weight,
                'Same color recommendation weight'
            );

        return $this;
    }

    public function getSameSizeRecommendationWeight(): float
    {
        return $this->sameSizeRecommendationWeight;
    }

    public function setSameSizeRecommendationWeight(
        float $weight
    ): static {
        $this->sameSizeRecommendationWeight =
            $this->validateFloatWeight(
                $weight,
                'Same size recommendation weight'
            );

        return $this;
    }

    public function getWishlistRecommendationWeight(): float
    {
        return $this->wishlistRecommendationWeight;
    }

    public function setWishlistRecommendationWeight(
        float $weight
    ): static {
        $this->wishlistRecommendationWeight =
            $this->validateFloatWeight(
                $weight,
                'Wishlist recommendation weight'
            );

        return $this;
    }

    public function getOrderHistoryRecommendationWeight(): float
    {
        return $this->orderHistoryRecommendationWeight;
    }

    public function setOrderHistoryRecommendationWeight(
        float $weight
    ): static {
        $this->orderHistoryRecommendationWeight =
            $this->validateFloatWeight(
                $weight,
                'Order history recommendation weight'
            );

        return $this;
    }

    public function getSearchHistoryRecommendationWeight(): float
    {
        return $this->searchHistoryRecommendationWeight;
    }

    public function setSearchHistoryRecommendationWeight(
        float $weight
    ): static {
        $this->searchHistoryRecommendationWeight =
            $this->validateFloatWeight(
                $weight,
                'Search history recommendation weight'
            );

        return $this;
    }

    public function getViewHistoryRecommendationWeight(): float
    {
        return $this->viewHistoryRecommendationWeight;
    }

    public function setViewHistoryRecommendationWeight(
        float $weight
    ): static {
        $this->viewHistoryRecommendationWeight =
            $this->validateFloatWeight(
                $weight,
                'View history recommendation weight'
            );

        return $this;
    }

    /*
     * Recommendation diversity
     */

    public function getMaxRecommendationPerCategory(): int
    {
        return $this->maxRecommendationPerCategory;
    }

    public function setMaxRecommendationPerCategory(int $value): static
    {
        if ($value < 1) {
            throw new \InvalidArgumentException(
                'Maximum recommendations per category must be at least 1.'
            );
        }

        $this->maxRecommendationPerCategory = $value;

        return $this;
    }

    public function getRecommendationDiversityPenalty(): float
    {
        return $this->recommendationDiversityPenalty;
    }

    public function setRecommendationDiversityPenalty(
        float $value
    ): static {
        $this->recommendationDiversityPenalty =
            $this->validateFloatWeight(
                $value,
                'Recommendation diversity penalty'
            );

        return $this;
    }

    /*
     * Recommendation switches
     */

    public function isRecommendationEnabled(): bool
    {
        return $this->recommendationEnabled;
    }

    public function setRecommendationEnabled(
        bool $recommendationEnabled
    ): static {
        $this->recommendationEnabled = $recommendationEnabled;

        return $this;
    }

    public function isRecommendationLoggingEnabled(): bool
    {
        return $this->recommendationLoggingEnabled;
    }

    public function setRecommendationLoggingEnabled(
        bool $recommendationLoggingEnabled
    ): static {
        $this->recommendationLoggingEnabled =
            $recommendationLoggingEnabled;

        return $this;
    }

    /*
     * Algorithm-specific settings
     */

    public function getAlgorithmSettings(): array
    {
        return $this->algorithmSettings;
    }

    public function setAlgorithmSettings(
        array $algorithmSettings
    ): static {
        $this->algorithmSettings = $algorithmSettings;

        return $this;
    }

    public function getAlgorithmSettingsSection(string $section): array
    {
        $value = $this->algorithmSettings[$section] ?? [];

        return is_array($value) ? $value : [];
    }

    public function getAlgorithmSetting(
        string $section,
        string $key,
        mixed $default = null
    ): mixed {
        $sectionSettings = $this->getAlgorithmSettingsSection($section);

        return $sectionSettings[$key] ?? $default;
    }

    public function setAlgorithmSetting(
        string $section,
        string $key,
        mixed $value
    ): static {
        if (
            !isset($this->algorithmSettings[$section])
            || !is_array($this->algorithmSettings[$section])
        ) {
            $this->algorithmSettings[$section] = [];
        }

        $this->algorithmSettings[$section][$key] = $value;

        return $this;
    }

    public function setAlgorithmSettingsSection(
        string $section,
        array $settings
    ): static {
        $this->algorithmSettings[$section] = $settings;

        return $this;
    }

    /*
     * Timestamps
     */

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(
        \DateTimeImmutable $createdAt
    ): static {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(
        \DateTimeImmutable $updatedAt
    ): static {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->touch();
    }

    /*
     * Validation helpers
     */

    private function validateIntegerWeight(
        int $value,
        string $fieldName
    ): int {
        if ($value < 0) {
            throw new \InvalidArgumentException(
                sprintf('%s cannot be negative.', $fieldName)
            );
        }

        return $value;
    }

    private function validateFloatWeight(
        float $value,
        string $fieldName
    ): float {
        if (!is_finite($value)) {
            throw new \InvalidArgumentException(
                sprintf('%s must be a finite number.', $fieldName)
            );
        }

        if ($value < 0) {
            throw new \InvalidArgumentException(
                sprintf('%s cannot be negative.', $fieldName)
            );
        }

        return $value;
    }
}