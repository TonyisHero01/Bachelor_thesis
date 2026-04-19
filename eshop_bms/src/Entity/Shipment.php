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

    public function __construct()
    {
        $this->events    = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // --- getters & setters ---

    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(Order $order): self { $this->order = $order; return $this; }

    public function getCarrier(): string { return $this->carrier; }
    public function setCarrier(string $carrier): self { $this->carrier = $carrier; return $this; }

    public function getTrackingNumber(): ?string { return $this->trackingNumber; }
    public function setTrackingNumber(?string $tn): self { $this->trackingNumber = $tn; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    /** @return Collection<int, ShipmentEvent> */
    public function getEvents(): Collection { return $this->events; }

    public function addEvent(ShipmentEvent $e): self
    {
        if (!$this->events->contains($e)) {
            $this->events->add($e);
            $e->setShipment($this);
        }
        return $this;
    }

    public function removeEvent(ShipmentEvent $e): self
    {
        $this->events->removeElement($e);
        return $this;
    }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $dt): self { $this->createdAt = $dt; return $this; }

    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function setUpdatedAt(\DateTime $dt): self { $this->updatedAt = $dt; return $this; }

    public function touch(): void { $this->updatedAt = new \DateTime(); }
}