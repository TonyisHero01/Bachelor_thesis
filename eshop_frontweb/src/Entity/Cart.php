<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "cart")]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: "App\Entity\Customer")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Customer $customer;

    #[ORM\ManyToOne(targetEntity: "App\Entity\Product")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Product $product;

    #[ORM\Column(type: "integer")]
    private int $quantity;

    #[ORM\Column(type: "datetime")]
    private \DateTime $addedAt;

    /**
     * Get the cart ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the customer.
     *
     * @return Customer|null
     */
    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    /**
     * Set the customer.
     *
     * @param Customer $customer
     * @return self
     */
    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    /**
     * Get the product.
     *
     * @return Product|null
     */
    public function getProduct(): ?Product
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
     * Get the added at date.
     *
     * @return \DateTime
     */
    public function getAddedAt(): \DateTime
    {
        return $this->addedAt;
    }

    /**
     * Set the added at date.
     *
     * @param \DateTime $addedAt
     * @return self
     */
    public function setAddedAt(\DateTime $addedAt): self
    {
        $this->addedAt = $addedAt;
        return $this;
    }
}