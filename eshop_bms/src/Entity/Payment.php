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

    /** 订单基准币种金额（与你的 Order.totalPrice 单位一致，建议=默认币种） */
    #[ORM\Column(type: "decimal", precision: 12, scale: 2)]
    private string $amount;

    /** 历史显示：旧字段，表示“订单基准币种”的三字码（可继续使用） */
    #[ORM\Column(type: "string", length: 3, options: ["default" => "CZK"])]
    private string $currency = 'CZK';

    /** 支付状态 */
    #[ORM\Column(type: "string", length: 20, options: ["default" => "PENDING"])]
    private string $status = self::STATUS_PENDING;

    /** 支付服务商；这里固定 mock */
    #[ORM\Column(type: "string", length: 32, options: ["default" => "mock"])]
    private string $provider = 'mock';

    /** 伪交易号 */
    #[ORM\Column(type: "string", length: 64, nullable: true)]
    private ?string $transactionId = null;

    /** 伪回调原文等 */
    #[ORM\Column(type: "json", nullable: true)]
    private ?array $payload = null;

    /** 付款币种（三字码）快照，如 EUR */
    #[ORM\Column(type: "string", length: 3, options: ["default" => "CZK"])]
    private string $currencyCode = 'CZK';

    /** 汇率快照：1 默认币种 = fxRate 付款币种 */
    #[ORM\Column(type: "decimal", precision: 18, scale: 8, options: ["default" => "1.00000000"])]
    private string $fxRate = '1.00000000';

    /** 付款币种金额（2位小数） */
    #[ORM\Column(type: "decimal", precision: 12, scale: 2)]
    private string $amountInCurrency = '0.00';

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $createdAt;

    #[ORM\Column(type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // --- getters & setters ---

    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?Order { return $this->order; }
    public function setOrder(Order $order): self { $this->order = $order; return $this; }

    public function getAmount(): string { return $this->amount; }
    public function setAmount(string $amount): self { $this->amount = $amount; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self { $this->currency = $currency; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $provider): self { $this->provider = $provider; return $this; }

    public function getTransactionId(): ?string { return $this->transactionId; }
    public function setTransactionId(?string $transactionId): self { $this->transactionId = $transactionId; return $this; }

    public function getPayload(): ?array { return $this->payload; }
    public function setPayload(?array $payload): self { $this->payload = $payload; return $this; }

    public function getCurrencyCode(): string { return $this->currencyCode; }
    public function setCurrencyCode(string $code): self { $this->currencyCode = $code; return $this; }

    public function getFxRate(): string { return $this->fxRate; }
    public function setFxRate(string $fxRate): self { $this->fxRate = $fxRate; return $this; }

    public function getAmountInCurrency(): string { return $this->amountInCurrency; }
    public function setAmountInCurrency(string $amt): self { $this->amountInCurrency = $amt; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $dt): self { $this->createdAt = $dt; return $this; }

    public function getUpdatedAt(): \DateTime { return $this->updatedAt; }
    public function setUpdatedAt(\DateTime $dt): self { $this->updatedAt = $dt; return $this; }

    public function touch(): void { $this->updatedAt = new \DateTime(); }
}