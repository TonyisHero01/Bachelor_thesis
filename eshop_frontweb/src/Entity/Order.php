<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: "orders")]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Customer $customer;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private float $totalPrice;

    #[ORM\Column(type: "text")]
    private string $address = "";

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $orderCreatedAt;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTime $pickupOrDeliveryAt = null;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $isCompleted = false;

    #[ORM\Column(type: "string", length: 50, options: ["default" => "PENDING"])]
    private string $paymentStatus = "PENDING";

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: "string", length: 50, options: ["default" => "PENDING"])]
    private string $deliveryStatus = "PENDING";

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, options: ["default" => 0.00])]
    private float $discount = 0.00;

    #[ORM\OneToMany(mappedBy: "order", targetEntity: OrderItem::class, cascade: ["persist", "remove"])]
    private Collection $orderItems;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "pickup"])]
    private string $deliveryMethod = "pickup";

    public function __construct()
    {
        $this->orderCreatedAt = new \DateTime();
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getOrderCreatedAt(): \DateTime
    {
        return $this->orderCreatedAt;
    }

    public function setOrderCreatedAt(?\DateTime $orderCreatedAt): self
    {
        $this->orderCreatedAt = $orderCreatedAt;
        return $this;
    }

    public function getPickupOrDeliveryAt(): ?\DateTime
    {
        return $this->pickupOrDeliveryAt;
    }

    public function setPickupOrDeliveryAt(?\DateTime $pickupOrDeliveryAt): self
    {
        $this->pickupOrDeliveryAt = $pickupOrDeliveryAt;
        return $this;
    }

    public function getIsCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): self
    {
        $this->isCompleted = $isCompleted;
        
        if ($isCompleted) {
            $this->paymentStatus = "COMPLETED";
            $this->deliveryStatus = "COMPLETED";
        }

        return $this;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getDeliveryStatus(): string
    {
        return $this->deliveryStatus;
    }

    public function setDeliveryStatus(string $deliveryStatus): self
    {
        $this->deliveryStatus = $deliveryStatus;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): self
    {
        $this->discount = $discount;
        return $this;
    }

    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    /**
     * Get Delivery Method (pickup/delivery)
     */
    public function getDeliveryMethod(): string
    {
        return $this->deliveryMethod;
    }

    /**
     * Set Delivery Method
     */
    public function setDeliveryMethod(string $deliveryMethod): self
    {
        if (!in_array($deliveryMethod, ["pickup", "delivery"])) {
            throw new \InvalidArgumentException("Invalid delivery method.");
        }
        $this->deliveryMethod = $deliveryMethod;
        return $this;
    }
}