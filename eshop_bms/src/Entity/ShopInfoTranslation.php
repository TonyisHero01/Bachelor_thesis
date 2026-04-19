<?php

namespace App\Entity;

use App\Repository\ShopInfoTranslationRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: ShopInfoTranslationRepository::class)]
class ShopInfoTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ShopInfo::class, inversedBy: "translations")]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ShopInfo $shopInfo;

    #[ORM\Column(length: 10)]
    private string $locale;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aboutUs = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $howToOrder = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $businessConditions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $privacyPolicy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shippingInfo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $payment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $refund = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShopInfo(): ShopInfo
    {
        return $this->shopInfo;
    }

    public function setShopInfo(ShopInfo $shopInfo): static
    {
        $this->shopInfo = $shopInfo;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    public function getAboutUs(): ?string
    {
        return $this->aboutUs;
    }

    public function setAboutUs(?string $aboutUs): static
    {
        $this->aboutUs = $aboutUs;
        return $this;
    }

    public function getHowToOrder(): ?string
    {
        return $this->howToOrder;
    }

    public function setHowToOrder(?string $howToOrder): static
    {
        $this->howToOrder = $howToOrder;
        return $this;
    }

    public function getBusinessConditions(): ?string
    {
        return $this->businessConditions;
    }

    public function setBusinessConditions(?string $businessConditions): static
    {
        $this->businessConditions = $businessConditions;
        return $this;
    }

    public function getPrivacyPolicy(): ?string
    {
        return $this->privacyPolicy;
    }

    public function setPrivacyPolicy(?string $privacyPolicy): static
    {
        $this->privacyPolicy = $privacyPolicy;
        return $this;
    }

    public function getShippingInfo(): ?string
    {
        return $this->shippingInfo;
    }

    public function setShippingInfo(?string $shippingInfo): static
    {
        $this->shippingInfo = $shippingInfo;
        return $this;
    }

    public function getPayment(): ?string
    {
        return $this->payment;
    }

    public function setPayment(?string $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    public function getRefund(): ?string
    {
        return $this->refund;
    }

    public function setRefund(?string $refund): static
    {
        $this->refund = $refund;
        return $this;
    }
}
