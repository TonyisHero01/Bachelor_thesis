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

    #[ORM\Column(type: "integer")]
    private int $quantity;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private string $unitPrice;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private string $subtotal;

    #[ORM\Column(type: 'string', length: 255)]
    private string $sku;

    /**
     * Get the order item ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the order.
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->order;
    }

    /**
     * Set the order.
     *
     * @param Order $order
     * @return self
     */
    public function setOrder(Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    /**
     * Get the product.
     *
     * @return Product
     */
    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * Set the product.
     *
     * @param Product $product
     * @return self
     */
    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    /**
     * Get the product name.
     *
     * @return string
     */
    public function getProductName(): string
    {
        return $this->productName;
    }

    /**
     * Set the product name.
     *
     * @param string $productName
     * @return self
     */
    public function setProductName(string $productName): self
    {
        $this->productName = $productName;
        return $this;
    }

    /**
     * Get the quantity.
     *
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Set the quantity.
     *
     * @param int $quantity
     * @return self
     */
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * Get the unit price.
     *
     * @return string
     */
    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    /**
     * Set the unit price.
     *
     * @param string $unitPrice
     * @return self
     */
    public function setUnitPrice(string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    /**
     * Get the subtotal.
     *
     * @return string
     */
    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    /**
     * Set the subtotal.
     *
     * @param string $subtotal
     * @return self
     */
    public function setSubtotal(string $subtotal): self
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    /**
     * Get the SKU.
     *
     * @return string
     */
    public function getSku(): string
    {
        return $this->sku;
    }
    public function setSku(string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    /**
     * Get the subtotal with tax.
     *
     * @return float
     */
    public function getSubtotalWithTax(): float
    {
        return round($this->getUnitPrice() * $this->getQuantity(), 2);
    }

    public function getTaxAmount(): float
    {
        $taxRate = $this->product->getTaxRate() / 100;

        $taxAmount = ($this->unitPrice * $this->quantity) - $this->subtotal;

        return round($taxAmount, 2);
    }
}