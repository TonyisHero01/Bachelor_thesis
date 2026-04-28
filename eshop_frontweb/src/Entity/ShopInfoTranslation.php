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

    /**
     * Get the shop info translation ID.
     *
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the shop info.
     *
     * @return ShopInfo
     */
    public function getShopInfo(): ShopInfo
    {
        return $this->shopInfo;
    }

    /**
     * Set the shop info.
     *
     * @param ShopInfo $shopInfo
     * @return self
     */
    public function setShopInfo(ShopInfo $shopInfo): static
    {
        $this->shopInfo = $shopInfo;
        return $this;
    }

    /**
     * Get the locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set the locale.
     *
     * @param string $locale
     * @return self
     */
    public function setLocale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Get the translated about us text.
     *
     * @return string|null
     */
    public function getAboutUs(): ?string
    {
        return $this->aboutUs;
    }

    /**
     * Set the translated about us text.
     *
     * @param string|null $aboutUs
     * @return self
     */
    public function setAboutUs(?string $aboutUs): static
    {
        $this->aboutUs = $aboutUs;
        return $this;
    }

    /**
     * Get the translated how to order text.
     *
     * @return string|null
     */
    public function getHowToOrder(): ?string
    {
        return $this->howToOrder;
    }

    /**
     * Set the translated how to order text.
     *
     * @param string|null $howToOrder
     * @return self
     */
    public function setHowToOrder(?string $howToOrder): static
    {
        $this->howToOrder = $howToOrder;
        return $this;
    }

    /**
     * Get the translated business conditions text.
     *
     * @return string|null
     */
    public function getBusinessConditions(): ?string
    {
        return $this->businessConditions;
    }

    /**
     * Set the translated business conditions text.
     *
     * @param string|null $businessConditions
     * @return self
     */
    public function setBusinessConditions(?string $businessConditions): static
    {
        $this->businessConditions = $businessConditions;
        return $this;
    }

    /**
     * Get the translated privacy policy text.
     *
     * @return string|null
     */
    public function getPrivacyPolicy(): ?string
    {
        return $this->privacyPolicy;
    }

    /**
     * Set the translated privacy policy text.
     *
     * @param string|null $privacyPolicy
     * @return self
     */
    public function setPrivacyPolicy(?string $privacyPolicy): static
    {
        $this->privacyPolicy = $privacyPolicy;
        return $this;
    }

    /**
     * Get the translated shipping information text.
     *
     * @return string|null
     */
    public function getShippingInfo(): ?string
    {
        return $this->shippingInfo;
    }

    /**
     * Set the translated shipping information text.
     *
     * @param string|null $shippingInfo
     * @return self
     */
    public function setShippingInfo(?string $shippingInfo): static
    {
        $this->shippingInfo = $shippingInfo;
        return $this;
    }

    /**
     * Get the translated payment information text.
     *
     * @return string|null
     */
    public function getPayment(): ?string
    {
        return $this->payment;
    }

    /**
     * Set the translated payment information text.
     *
     * @param string|null $payment
     * @return self
     */
    public function setPayment(?string $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    /**
     * Get the translated refund information text.
     *
     * @return string|null
     */
    public function getRefund(): ?string
    {
        return $this->refund;
    }

    /**
     * Set the translated refund information text.
     *
     * @param string|null $refund
     * @return self
     */
    public function setRefund(?string $refund): static
    {
        $this->refund = $refund;
        return $this;
    }
}