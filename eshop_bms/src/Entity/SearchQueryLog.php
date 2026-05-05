<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'search_query_log')]
class SearchQueryLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $query;

    #[ORM\Column(type: 'string', length: 20)]
    private string $method;

    #[ORM\Column(type: 'integer')]
    private int $resultCount;

    #[ORM\Column(type: 'float')]
    private float $responseTimeMs;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ===== getters & setters =====

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): self
    {
        $this->query = $query;
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function getResultCount(): int
    {
        return $this->resultCount;
    }

    public function setResultCount(int $resultCount): self
    {
        $this->resultCount = $resultCount;
        return $this;
    }

    public function getResponseTimeMs(): float
    {
        return $this->responseTimeMs;
    }

    public function setResponseTimeMs(float $responseTimeMs): self
    {
        $this->responseTimeMs = $responseTimeMs;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}