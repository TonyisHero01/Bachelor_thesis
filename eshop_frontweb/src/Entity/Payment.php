<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "payments")]
class Payment
{
    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_SUCCESS  = 'SUCCESS';
    public const STATUS_FAILED   = 'FAILED';
    public const STATUS_REFUNDED = 'REFUNDED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: "payments")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Order $order = null;

    #[ORM\Column(type: "decimal", precision: 12, scale: 2)]
    private string $amount;

    #[ORM\Column(type: "string", length: 3, options: ["default" => "CZK"])]
    private string $currency = 'CZK';

    #[ORM\Column(type: "string", length: 20, options: ["default" => "PENDING"])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: "string", length: 32, options: ["default" => "mock"])]
    private string $provider = 'mock';

    #[ORM\Column(type: "string", length: 64, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(type: "json", nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(type: "string", length: 3, options: ["default" => "CZK"])]
    private string $currencyCode = 'CZK';

    #[ORM\Column(type: "decimal", precision: 18, scale: 8, options: ["default" => "1.00000000"])]
    private string $fxRate = '1.00000000';

    #[ORM\Column(type: "decimal", precision: 12, scale: 2)]
    private string $amountInCurrency = '0.00';

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $createdAt;

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $updatedAt;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * Get the payment ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the order.
     *
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        return $this->order;
    }

    /**
     * Set the order.
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
     * Get the base currency amount.
     *
     * @return string
     */
    public function getAmount(): string
    {
        return $this->amount;
    }

    /**
     * Set the base currency amount.
     *
     * @param string $amount
     * @return self
     */
    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Get the base currency code.
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Set the base currency code.
     *
     * @param string $currency
     * @return self
     */
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Get the payment status.
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set the payment status.
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
     * Get the payment provider.
     *
     * @return string
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Set the payment provider.
     *
     * @param string $provider
     * @return self
     */
    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Get the transaction ID.
     *
     * @return string|null
     */
    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    /**
     * Set the transaction ID.
     *
     * @param string|null $transactionId
     * @return self
     */
    public function setTransactionId(?string $transactionId): self
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    /**
     * Get the payload data.
     *
     * @return array|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * Set the payload data.
     *
     * @param array|null $payload
     * @return self
     */
    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Get the payment currency code.
     *
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    /**
     * Set the payment currency code.
     *
     * @param string $code
     * @return self
     */
    public function setCurrencyCode(string $code): self
    {
        $this->currencyCode = $code;
        return $this;
    }

    /**
     * Get the exchange rate.
     *
     * @return string
     */
    public function getFxRate(): string
    {
        return $this->fxRate;
    }

    /**
     * Set the exchange rate.
     *
     * @param string $fxRate
     * @return self
     */
    public function setFxRate(string $fxRate): self
    {
        $this->fxRate = $fxRate;
        return $this;
    }

    /**
     * Get the amount in payment currency.
     *
     * @return string
     */
    public function getAmountInCurrency(): string
    {
        return $this->amountInCurrency;
    }

    /**
     * Set the amount in payment currency.
     *
     * @param string $amount
     * @return self
     */
    public function setAmountInCurrency(string $amount): self
    {
        $this->amountInCurrency = $amount;
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
     * @param \DateTime $dt
     * @return self
     */
    public function setCreatedAt(\DateTime $dt): self
    {
        $this->createdAt = $dt;
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
     * @param \DateTime $dt
     * @return self
     */
    public function setUpdatedAt(\DateTime $dt): self
    {
        $this->updatedAt = $dt;
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