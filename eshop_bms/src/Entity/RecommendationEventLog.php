<?php

namespace App\Entity;

use App\Repository\RecommendationEventLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecommendationEventLogRepository::class)]
#[ORM\Table(name: 'recommendation_event_log')]
class RecommendationEventLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private ?string $sessionId = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 64)]
    private ?string $pageType = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sourceSku = null;

    #[ORM\Column(length: 64)]
    private ?string $recommendedSku = null;

    #[ORM\Column(length: 64)]
    private ?string $algorithm = null;

    #[ORM\Column]
    private ?int $rankPosition = null;

    #[ORM\Column(nullable: true)]
    private ?float $score = null;

    #[ORM\Column(length: 32)]
    private ?string $eventType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getPageType(): ?string
    {
        return $this->pageType;
    }

    public function setPageType(string $pageType): self
    {
        $this->pageType = $pageType;

        return $this;
    }

    public function getSourceSku(): ?string
    {
        return $this->sourceSku;
    }

    public function setSourceSku(?string $sourceSku): self
    {
        $this->sourceSku = $sourceSku;

        return $this;
    }

    public function getRecommendedSku(): ?string
    {
        return $this->recommendedSku;
    }

    public function setRecommendedSku(string $recommendedSku): self
    {
        $this->recommendedSku = $recommendedSku;

        return $this;
    }

    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }

    public function setAlgorithm(string $algorithm): self
    {
        $this->algorithm = $algorithm;

        return $this;
    }

    public function getRankPosition(): ?int
    {
        return $this->rankPosition;
    }

    public function setRankPosition(int $rankPosition): self
    {
        $this->rankPosition = $rankPosition;

        return $this;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(?float $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}