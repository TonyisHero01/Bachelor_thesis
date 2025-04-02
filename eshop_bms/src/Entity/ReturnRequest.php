<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ReturnRequestRepository;

#[ORM\Entity(repositoryClass: ReturnRequestRepository::class)]
#[ORM\Table(name: "return_requests")]
class ReturnRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Order $order;

    #[ORM\Column(type: "string", length: 255)]
    private string $userEmail;

    #[ORM\Column(type: "string", length: 20)]
    private string $userPhone;

    #[ORM\Column(type: "string", length: 100)]
    private string $userName;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $returnReason = null;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "pending"])]
    private string $status = "pending";

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $userMessage = null;

    #[ORM\Column(type: "text")]
    private string $productSkus;  // 存储多个 SKU

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $requestDate;

    public function __construct()
    {
        $this->requestDate = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getUserEmail(): string
    {
        return $this->userEmail;
    }

    public function setUserEmail(string $userEmail): self
    {
        $this->userEmail = $userEmail;
        return $this;
    }

    public function getUserPhone(): string
    {
        return $this->userPhone;
    }

    public function setUserPhone(string $userPhone): self
    {
        $this->userPhone = $userPhone;
        return $this;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function setUserName(string $userName): self
    {
        $this->userName = $userName;
        return $this;
    }

    public function getReturnReason(): ?string
    {
        return $this->returnReason;
    }

    public function setReturnReason(?string $returnReason): self
    {
        $this->returnReason = $returnReason;
        return $this;
    }

    public function getUserMessage(): ?string
    {
        return $this->userMessage;
    }

    public function setUserMessage(?string $userMessage): self
    {
        $this->userMessage = $userMessage;
        return $this;
    }

    public function getProductSkus(): string
    {
        return $this->productSkus;
    }

    public function setProductSkus(string $productSkus): self
    {
        $this->productSkus = $productSkus;
        return $this;
    }

    public function getRequestDate(): \DateTimeInterface
    {
        return $this->requestDate;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, ['pending', 'accepted', 'rejected'])) {
            throw new \InvalidArgumentException("Invalid status value.");
        }
        $this->status = $status;
        return $this;
    }
}