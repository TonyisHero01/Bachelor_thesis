<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Payment;
use App\Entity\Shipment;

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
    private string $totalPrice;

    #[ORM\Column(type: "text")]
    private ?string $address = null;

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

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, options: ["default" => "0.00"])]
    private string $discount;

    #[ORM\OneToMany(mappedBy: "order", targetEntity: OrderItem::class, cascade: ["persist", "remove"])]
    private Collection $orderItems;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "pickup"])]
    private string $deliveryMethod = "pickup";

    #[ORM\OneToMany(mappedBy: "order", targetEntity: Payment::class, cascade: ["persist", "remove"])]
    #[ORM\OrderBy(["createdAt" => "DESC"])]
    private Collection $payments;

    #[ORM\OneToOne(mappedBy: "order", targetEntity: Shipment::class, cascade: ["persist", "remove"])]
    private ?Shipment $shipment = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->orderCreatedAt = new \DateTime();
        $this->orderItems = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    /**
     * Get the order ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the customer.
     *
     * @return Customer
     */
    public function getCustomer(): Customer
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
     * Get the total price.
     *
     * @return string
     */
    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    /**
     * Set the total price.
     *
     * @param string $totalPrice
     * @return self
     */
    public function setTotalPrice(string $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    /**
     * Get the address.
     *
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address ?? 'No address provided';
    }

    /**
     * Set the address.
     *
     * @param string $address
     * @return self
     */
    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    /**
     * Get the order creation date.
     *
     * @return \DateTime
     */
    public function getOrderCreatedAt(): \DateTime
    {
        return $this->orderCreatedAt;
    }

    /**
     * Set the order creation date.
     *
     * @param \DateTime|null $orderCreatedAt
     * @return self
     */
    public function setOrderCreatedAt(?\DateTime $orderCreatedAt): self
    {
        $this->orderCreatedAt = $orderCreatedAt;
        return $this;
    }

    /**
     * Get the pickup or delivery date.
     *
     * @return \DateTime|null
     */
    public function getPickupOrDeliveryAt(): ?\DateTime
    {
        return $this->pickupOrDeliveryAt;
    }

    /**
     * Set the pickup or delivery date.
     *
     * @param \DateTime|null $pickupOrDeliveryAt
     * @return self
     */
    public function setPickupOrDeliveryAt(?\DateTime $pickupOrDeliveryAt): self
    {
        $this->pickupOrDeliveryAt = $pickupOrDeliveryAt;
        return $this;
    }

    /**
     * Get whether the order is completed.
     *
     * @return bool
     */
    public function getIsCompleted(): bool
    {
        return $this->isCompleted;
    }

    /**
     * Set whether the order is completed.
     *
     * @param bool $isCompleted
     * @return self
     */
    public function setIsCompleted(bool $isCompleted): self
    {
        $this->isCompleted = $isCompleted;

        if ($isCompleted) {
            $this->paymentStatus = "COMPLETED";
            $this->deliveryStatus = "COMPLETED";
        }

        return $this;
    }

    /**
     * Get the payment status.
     *
     * @return string
     */
    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    /**
     * Set the payment status.
     *
     * @param string $paymentStatus
     * @return self
     */
    public function setPaymentStatus(string $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    /**
     * Get the payment method.
     *
     * @return string|null
     */
    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    /**
     * Set the payment method.
     *
     * @param string|null $paymentMethod
     * @return self
     */
    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    /**
     * Get the delivery status.
     *
     * @return string
     */
    public function getDeliveryStatus(): string
    {
        return $this->deliveryStatus;
    }

    /**
     * Set the delivery status.
     *
     * @param string $deliveryStatus
     * @return self
     */
    public function setDeliveryStatus(string $deliveryStatus): self
    {
        $this->deliveryStatus = $deliveryStatus;
        return $this;
    }

    /**
     * Get the order notes.
     *
     * @return string|null
     */
    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * Set the order notes.
     *
     * @param string|null $notes
     * @return self
     */
    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * Get the discount amount.
     *
     * @return string
     */
    public function getDiscount(): string
    {
        return $this->discount;
    }

    /**
     * Set the discount amount.
     *
     * @param string $discount
     * @return self
     */
    public function setDiscount(string $discount): self
    {
        $this->discount = $discount;
        return $this;
    }

    /**
     * Get the order items.
     *
     * @return Collection
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    /**
     * Get the delivery method.
     *
     * @return string
     */
    public function getDeliveryMethod(): string
    {
        return $this->deliveryMethod;
    }

    /**
     * Set the delivery method.
     *
     * @param string $deliveryMethod
     * @return self
     */
    public function setDeliveryMethod(string $deliveryMethod): self
    {
        if (!in_array($deliveryMethod, ["pickup", "delivery"])) {
            throw new \InvalidArgumentException("Invalid delivery method.");
        }

        $this->deliveryMethod = $deliveryMethod;
        return $this;
    }

    /**
     * Get the payments.
     *
     * @return Collection
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    /**
     * Add a payment to the order.
     *
     * @param Payment $payment
     * @return self
     */
    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setOrder($this);
        }

        return $this;
    }

    /**
     * Get the latest payment.
     *
     * @return Payment|null
     */
    public function getLatestPayment(): ?Payment
    {
        return $this->payments->first() ?: null;
    }

    /**
     * Get the shipment.
     *
     * @return Shipment|null
     */
    public function getShipment(): ?Shipment
    {
        return $this->shipment;
    }

    /**
     * Set the shipment.
     *
     * @param Shipment $shipment
     * @return self
     */
    public function setShipment(Shipment $shipment): self
    {
        $this->shipment = $shipment;
        return $this;
    }
}