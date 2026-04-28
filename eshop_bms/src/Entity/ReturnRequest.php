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
    private string $productSkus;

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $requestDate;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->requestDate = new \DateTime();
    }

    /**
     * Get the return request ID.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the related order.
     *
     * @return Order
     */
    public function getOrder(): Order
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
     * Get the user email.
     *
     * @return string
     */
    public function getUserEmail(): string
    {
        return $this->userEmail;
    }

    /**
     * Set the user email.
     *
     * @param string $userEmail
     * @return self
     */
    public function setUserEmail(string $userEmail): self
    {
        $this->userEmail = $userEmail;
        return $this;
    }

    /**
     * Get the user phone number.
     *
     * @return string
     */
    public function getUserPhone(): string
    {
        return $this->userPhone;
    }

    /**
     * Set the user phone number.
     *
     * @param string $userPhone
     * @return self
     */
    public function setUserPhone(string $userPhone): self
    {
        $this->userPhone = $userPhone;
        return $this;
    }

    /**
     * Get the user name.
     *
     * @return string
     */
    public function getUserName(): string
    {
        return $this->userName;
    }

    /**
     * Set the user name.
     *
     * @param string $userName
     * @return self
     */
    public function setUserName(string $userName): self
    {
        $this->userName = $userName;
        return $this;
    }

    /**
     * Get the return reason.
     *
     * @return string|null
     */
    public function getReturnReason(): ?string
    {
        return $this->returnReason;
    }

    /**
     * Set the return reason.
     *
     * @param string|null $returnReason
     * @return self
     */
    public function setReturnReason(?string $returnReason): self
    {
        $this->returnReason = $returnReason;
        return $this;
    }

    /**
     * Get the user message.
     *
     * @return string|null
     */
    public function getUserMessage(): ?string
    {
        return $this->userMessage;
    }

    /**
     * Set the user message.
     *
     * @param string|null $userMessage
     * @return self
     */
    public function setUserMessage(?string $userMessage): self
    {
        $this->userMessage = $userMessage;
        return $this;
    }

    /**
     * Get product SKUs.
     *
     * @return string
     */
    public function getProductSkus(): string
    {
        return $this->productSkus;
    }

    /**
     * Set product SKUs.
     *
     * @param string $productSkus
     * @return self
     */
    public function setProductSkus(string $productSkus): self
    {
        $this->productSkus = $productSkus;
        return $this;
    }

    /**
     * Get the request date.
     *
     * @return \DateTimeInterface
     */
    public function getRequestDate(): \DateTimeInterface
    {
        return $this->requestDate;
    }

    /**
     * Get the request status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set the request status.
     *
     * @param string $status
     * @return self
     */
    public function setStatus(string $status): self
    {
        if (!in_array($status, ['pending', 'accepted', 'rejected'])) {
            throw new \InvalidArgumentException("Invalid status value.");
        }

        $this->status = $status;
        return $this;
    }
}