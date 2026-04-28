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

    #[ORM\Column(type: "string", length: 32)]
    private string $eventCode;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $eventTime;

    #[ORM\Column(type: "string", length: 128, nullable: true)]
    private ?string $location = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->eventTime = new \DateTime();
    }

    /**
     * Get the event ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the related shipment.
     *
     * @return Shipment|null
     */
    public function getShipment(): ?Shipment
    {
        return $this->shipment;
    }

    /**
     * Set the related shipment.
     *
     * @param Shipment $shipment
     * @return self
     */
    public function setShipment(Shipment $shipment): self
    {
        $this->shipment = $shipment;
        return $this;
    }

    /**
     * Get the event code.
     *
     * @return string
     */
    public function getEventCode(): string
    {
        return $this->eventCode;
    }

    /**
     * Set the event code.
     *
     * @param string $code
     * @return self
     */
    public function setEventCode(string $code): self
    {
        $this->eventCode = $code;
        return $this;
    }

    /**
     * Get the event description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the event description.
     *
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the event timestamp.
     *
     * @return \DateTime
     */
    public function getEventTime(): \DateTime
    {
        return $this->eventTime;
    }

    /**
     * Set the event timestamp.
     *
     * @param \DateTime $eventTime
     * @return self
     */
    public function setEventTime(\DateTime $eventTime): self
    {
        $this->eventTime = $eventTime;
        return $this;
    }

    /**
     * Get the event location.
     *
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * Set the event location.
     *
     * @param string|null $location
     * @return self
     */
    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }
}