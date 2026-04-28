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

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * Get the shop info ID.
     *
     * @return int|null
     */
    public function getId(): ?int { return $this->id; }

    /**
     * Get the e-shop name.
     *
     * @return string|null
     */
    public function getEshopName(): ?string { return $this->eshopName; }

    /**
     * Set the e-shop name.
     *
     * @param string|null $eshopName
     * @return self
     */
    public function setEshopName(?string $eshopName): static { $this->eshopName = $eshopName; return $this; }

    /**
     * Get the address.
     *
     * @return string|null
     */
    public function getAddress(): ?string { return $this->address; }

    /**
     * Set the address.
     *
     * @param string|null $address
     * @return self
     */
    public function setAddress(?string $address): static { $this->address = $address; return $this; }

    /**
     * Get the telephone number.
     *
     * @return string|null
     */
    public function getTelephone(): ?string { return $this->telephone; }

    /**
     * Set the telephone number.
     *
     * @param string|null $telephone
     * @return self
     */
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    /**
     * Get the email address.
     *
     * @return string|null
     */
    public function getEmail(): ?string { return $this->email; }

    /**
     * Set the email address.
     *
     * @param string|null $email
     * @return self
     */
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    /**
     * Get the about us text.
     *
     * @return string|null
     */
    public function getAboutUs(): ?string { return $this->aboutUs; }

    /**
     * Set the about us text.
     *
     * @param string|null $aboutUs
     * @return self
     */
    public function setAboutUs(?string $aboutUs): static { $this->aboutUs = $aboutUs; return $this; }

    /**
     * Get the how to order text.
     *
     * @return string|null
     */
    public function getHowToOrder(): ?string { return $this->howToOrder; }

    /**
     * Set the how to order text.
     *
     * @param string|null $howToOrder
     * @return self
     */
    public function setHowToOrder(?string $howToOrder): static { $this->howToOrder = $howToOrder; return $this; }

    /**
     * Get the business conditions text.
     *
     * @return string|null
     */
    public function getBusinessConditions(): ?string { return $this->businessConditions; }

    /**
     * Set the business conditions text.
     *
     * @param string|null $businessConditions
     * @return self
     */
    public function setBusinessConditions(?string $businessConditions): static { $this->businessConditions = $businessConditions; return $this; }

    /**
     * Get the privacy policy text.
     *
     * @return string|null
     */
    public function getPrivacyPolicy(): ?string { return $this->privacyPolicy; }

    /**
     * Set the privacy policy text.
     *
     * @param string|null $privacyPolicy
     * @return self
     */
    public function setPrivacyPolicy(?string $privacyPolicy): static { $this->privacyPolicy = $privacyPolicy; return $this; }

    /**
     * Get the shipping information text.
     *
     * @return string|null
     */
    public function getShippingInfo(): ?string { return $this->shippingInfo; }

    /**
     * Set the shipping information text.
     *
     * @param string|null $shippingInfo
     * @return self
     */
    public function setShippingInfo(?string $shippingInfo): static { $this->shippingInfo = $shippingInfo; return $this; }

    /**
     * Get the payment information text.
     *
     * @return string|null
     */
    public function getPayment(): ?string { return $this->payment; }

    /**
     * Set the payment information text.
     *
     * @param string|null $payment
     * @return self
     */
    public function setPayment(?string $payment): static { $this->payment = $payment; return $this; }

    /**
     * Get the refund information text.
     *
     * @return string|null
     */
    public function getRefund(): ?string { return $this->refund; }

    /**
     * Set the refund information text.
     *
     * @param string|null $refund
     * @return self
     */
    public function setRefund(?string $refund): static { $this->refund = $refund; return $this; }

    /**
     * Get the logo path.
     *
     * @return string|null
     */
    public function getLogo(): ?string { return $this->logo; }

    /**
     * Set the logo path.
     *
     * @param string|null $logo
     * @return self
     */
    public function setLogo(?string $logo): static { $this->logo = $logo; return $this; }

    /**
     * Get carousel pictures.
     *
     * @return array|null
     */
    public function getCarouselPictures(): ?array { return $this->carouselPictures; }

    /**
     * Set carousel pictures.
     *
     * @param array|null $carouselPictures
     * @return self
     */
    public function setCarouselPictures(?array $carouselPictures): static { $this->carouselPictures = $carouselPictures; return $this; }

    /**
     * Get the company name.
     *
     * @return string|null
     */
    public function getCompanyName(): ?string { return $this->companyName; }

    /**
     * Set the company name.
     *
     * @param string|null $companyName
     * @return self
     */
    public function setCompanyName(?string $companyName): static { $this->companyName = $companyName; return $this; }

    /**
     * Get the company identification number.
     *
     * @return string|null
     */
    public function getCin(): ?string { return $this->cin; }

    /**
     * Set the company identification number.
     *
     * @param string|null $cin
     * @return self
     */
    public function setCin(?string $cin): static { $this->cin = $cin; return $this; }

    /**
     * Check if prices are hidden.
     *
     * @return bool
     */
    public function getHidePrices(): bool { return $this->hidePrices; }

    /**
     * Set whether prices are hidden.
     *
     * @param bool $hidePrices
     * @return self
     */
    public function setHidePrices(bool $hidePrices): static { $this->hidePrices = $hidePrices; return $this; }

    /**
     * Get translations collection.
     *
     * @return Collection
     */
    public function getTranslations(): Collection { return $this->translations; }

    /**
     * Add a translation.
     *
     * @param ShopInfoTranslation $translation
     * @return self
     */
    public function addTranslation(ShopInfoTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations[] = $translation;
            $translation->setShopInfo($this);
        }

        return $this;
    }

    /**
     * Remove a translation.
     *
     * @param ShopInfoTranslation $translation
     * @return self
     */
    public function removeTranslation(ShopInfoTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getShopInfo() === $this) {
                $translation->setShopInfo(null);
            }
        }

        return $this;
    }

    /**
     * Get a translated field value by field name and locale.
     *
     * @param string $field
     * @param string $locale
     * @return string|null
     */
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