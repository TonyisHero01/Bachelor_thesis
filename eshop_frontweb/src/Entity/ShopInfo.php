<?php

namespace App\Entity;

use App\Repository\ShopInfoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShopInfoRepository::class)]
class ShopInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $eshopName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $email = null;

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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(nullable: true)]
    private ?array $carouselPictures = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cin = null;

    #[ORM\Column(type: "boolean", options: ["default" => false])]
    private bool $hidePrices = false;

    #[ORM\OneToMany(mappedBy: 'shopInfo', targetEntity: ShopInfoTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getEshopName(): ?string { return $this->eshopName; }
    public function setEshopName(?string $eshopName): static { $this->eshopName = $eshopName; return $this; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): static { $this->address = $address; return $this; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }
    public function getAboutUs(): ?string { return $this->aboutUs; }
    public function setAboutUs(?string $aboutUs): static { $this->aboutUs = $aboutUs; return $this; }
    public function getHowToOrder(): ?string { return $this->howToOrder; }
    public function setHowToOrder(?string $howToOrder): static { $this->howToOrder = $howToOrder; return $this; }
    public function getBusinessConditions(): ?string { return $this->businessConditions; }
    public function setBusinessConditions(?string $businessConditions): static { $this->businessConditions = $businessConditions; return $this; }
    public function getPrivacyPolicy(): ?string { return $this->privacyPolicy; }
    public function setPrivacyPolicy(?string $privacyPolicy): static { $this->privacyPolicy = $privacyPolicy; return $this; }
    public function getShippingInfo(): ?string { return $this->shippingInfo; }
    public function setShippingInfo(?string $shippingInfo): static { $this->shippingInfo = $shippingInfo; return $this; }
    public function getPayment(): ?string { return $this->payment; }
    public function setPayment(?string $payment): static { $this->payment = $payment; return $this; }
    public function getRefund(): ?string { return $this->refund; }
    public function setRefund(?string $refund): static { $this->refund = $refund; return $this; }
    public function getLogo(): ?string { return $this->logo; }
    public function setLogo(?string $logo): static { $this->logo = $logo; return $this; }
    public function getCarouselPictures(): ?array { return $this->carouselPictures; }
    public function setCarouselPictures(?array $carouselPictures): static { $this->carouselPictures = $carouselPictures; return $this; }
    public function getCompanyName(): ?string { return $this->companyName; }
    public function setCompanyName(?string $companyName): static { $this->companyName = $companyName; return $this; }
    public function getCin(): ?string { return $this->cin; }
    public function setCin(?string $cin): static { $this->cin = $cin; return $this; }
    public function getHidePrices(): bool { return $this->hidePrices; }
    public function setHidePrices(bool $hidePrices): static { $this->hidePrices = $hidePrices; return $this; }

    public function getTranslations(): Collection { return $this->translations; }

    public function addTranslation(ShopInfoTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations[] = $translation;
            $translation->setShopInfo($this);
        }
        return $this;
    }

    public function removeTranslation(ShopInfoTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getShopInfo() === $this) {
                $translation->setShopInfo(null);
            }
        }
        return $this;
    }

    public function getTranslatedField(string $field, string $locale): ?string
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                $getter = 'get' . ucfirst($field);
                if (method_exists($translation, $getter)) {
                    $value = $translation->$getter();
                    if ($value !== null) {
                        return $value;
                    }
                }
            }
        }

        $ownGetter = 'get' . ucfirst($field);
        return method_exists($this, $ownGetter) ? $this->$ownGetter() : null;
    }
}