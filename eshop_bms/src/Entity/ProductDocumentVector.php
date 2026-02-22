<?php
// src/Entity/ProductDocumentVector.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)] // 🔒 标记只读，避免 Doctrine 误操作
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

    // Getters
    public function getSku(): string { return $this->sku; }
    public function getDocument(): ?string { return $this->document; }
    public function getVector(): ?string { return $this->vector; }
}