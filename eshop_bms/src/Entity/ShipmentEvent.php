<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "shipment_events")]
class ShipmentEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shipment::class, inversedBy: "events")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Shipment $shipment = null;

    /** 简短事件码：created/scanned/departed/arrived/delivered... */
    #[ORM\Column(type: "string", length: 32)]
    private string $eventCode;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $eventTime;

    #[ORM\Column(type: "string", length: 128, nullable: true)]
    private ?string $location = null;

    public function __construct()
    {
        $this->eventTime = new \DateTime();
    }

    // --- getters & setters ---

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(Shipment $shipment): self { $this->shipment = $shipment; return $this; }

    public function getEventCode(): string { return $this->eventCode; }
    public function setEventCode(string $code): self { $this->eventCode = $code; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getEventTime(): \DateTime { return $this->eventTime; }
    public function setEventTime(\DateTime $dt): self { $this->eventTime = $dt; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $loc): self { $this->location = $loc; return $this; }
}