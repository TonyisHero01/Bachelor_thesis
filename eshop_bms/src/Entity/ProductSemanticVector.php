<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "product_semantic_vector")]
class ProductSemanticVector
{
    #[ORM\Id]
    #[ORM\Column(type: "integer")]
    private ?int $product_id = null;

    /**
     * IMPORTANT:
     * Doctrine does not natively support pgvector type.
     *
     * We therefore store the field as string in Doctrine,
     * and use raw SQL for vector operations.
     */
    #[ORM\Column(type: "string", length: 20000)]
    private ?string $embedding = null;

    #[ORM\Column(type: "datetime")]
    private ?\DateTimeInterface $updated_at = null;

    /**
     * Get product ID.
     */
    public function getProductId(): ?int
    {
        return $this->product_id;
    }

    /**
     * Set product ID.
     */
    public function setProductId(int $product_id): self
    {
        $this->product_id = $product_id;

        return $this;
    }

    /**
     * Get embedding vector string.
     */
    public function getEmbedding(): ?string
    {
        return $this->embedding;
    }

    /**
     * Set embedding vector string.
     */
    public function setEmbedding(?string $embedding): self
    {
        $this->embedding = $embedding;

        return $this;
    }

    /**
     * Get updated timestamp.
     */
    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    /**
     * Set updated timestamp.
     */
    public function setUpdatedAt(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;

        return $this;
    }
}