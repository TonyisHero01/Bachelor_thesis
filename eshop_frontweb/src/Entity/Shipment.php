<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: "shipments")]
class Shipment
{
    public const STATUS_CREATED          = 'CREATED';
    public const STATUS_PACKED           = 'PACKED';
    public const STATUS_SHIPPED          = 'SHIPPED';
    public const STATUS_IN_TRANSIT       = 'IN_TRANSIT';
    public const STATUS_OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    public const STATUS_DELIVERED        = 'DELIVERED';
    public const STATUS_RETURNED         = 'RETURNED';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: "shipment", targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Order $order = null;

    #[ORM\Column(type: "string", length: 64, options: ["default" => "MockExpress"])]
    private string $carrier = 'MockExpress';

    #[ORM\Column(type: "string", length: 64, unique: true, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(type: "string", length: 32, options: ["default" => "CREATED"])]
    private string $status = self::STATUS_CREATED;

    #[ORM\OneToMany(mappedBy: "shipment", targetEntity: ShipmentEvent::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["eventTime" => "DESC"])]
    private Collection $events;

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $createdAt;

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $updatedAt;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->events    = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Get the shipment ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the related order.
     *
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        return $this->order;
    }

    /**
     * Set the related order.
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
     * Get the carrier name.
     *
     * @return string
     */
    public function getCarrier(): string
    {
        return $this->carrier;
    }

    /**
     * Set the carrier name.
     *
     * @param string $carrier
     * @return self
     */
    public function setCarrier(string $carrier): self
    {
        $this->carrier = $carrier;
        return $this;
    }

    /**
     * Get the tracking number.
     *
     * @return string|null
     */
    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    /**
     * Set the tracking number.
     *
     * @param string|null $trackingNumber
     * @return self
     */
    public function setTrackingNumber(?string $trackingNumber): self
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    /**
     * Get the shipment status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set the shipment status.
     *
     * @param string $status
     * @return self
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Get shipment events.
     *
     * @return Collection
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    /**
     * Add a shipment event.
     *
     * @param ShipmentEvent $event
     * @return self
     */
    public function addEvent(ShipmentEvent $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setShipment($this);
        }

        return $this;
    }

    /**
     * Remove a shipment event.
     *
     * @param ShipmentEvent $event
     * @return self
     */
    public function removeEvent(ShipmentEvent $event): self
    {
        $this->events->removeElement($event);
        return $this;
    }

    /**
     * Get the creation timestamp.
     *
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Set the creation timestamp.
     *
     * @param \DateTime $createdAt
     * @return self
     */
    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get the last update timestamp.
     *
     * @return \DateTime
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Set the last update timestamp.
     *
     * @param \DateTime $updatedAt
     * @return self
     */
    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Update the modification timestamp.
     */
    public function touch(): void
    {
        $this->updatedAt = new \DateTime();
    }
}