<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: "product_document_vector")]
class ProductDocumentVector
{
    #[ORM\Id]
    #[ORM\Column(type: "string", length: 255)]
    private string $sku;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $document = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $vector = null;

    /**
     * Get the product SKU.
     *
     * @return string
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * Get the document content.
     *
     * @return string|null
     */
    public function getDocument(): ?string
    {
        return $this->document;
    }

    /**
     * Get the vector representation.
     *
     * @return string|null
     */
    public function getVector(): ?string
    {
        return $this->vector;
    }
}