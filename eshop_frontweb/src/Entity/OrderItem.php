<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "order_items")]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: "orderItems")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(type: "string", length: 255)]
    private string $productName;

    #[ORM\Column(type: "string", length: 255, nullable: false)]
    private string $sku;

    #[ORM\Column(type: "integer")]
    private int $quantity;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $unitPrice;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $subtotal;

    // Getters and Setters...

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): self
    {
        $this->productName = $productName;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function setSubtotal(): self
    {
        if (!isset($this->product)) {
            throw new \LogicException("OrderItem product must be set before calling setSubtotal().");
        }

        $this->subtotal = ($this->unitPrice / (1 + $this->product->getTaxRate() / 100)) * $this->quantity;
        return $this;
    }

    public function getTaxAmount(): float
    {
        // 获取商品的税率（假设 taxRate 存储为百分比，如 21 表示 21%）
        $taxRate = $this->product->getTaxRate() / 100;

        // 计算税费 = 含税价格 - 不含税价格
        $taxAmount = ($this->unitPrice * $this->quantity) - $this->subtotal;

        return round($taxAmount, 2);
    }

    public function setSku(string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    public function getSku(): string
    {
        return $this->sku;
    }
}