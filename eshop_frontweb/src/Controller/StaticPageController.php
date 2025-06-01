<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StaticPageController extends BaseController
{
    private function getShopInfo(EntityManagerInterface $em): ?ShopInfo
    {
        return $em->getRepository(ShopInfo::class)->findWithTranslations();
    }

    #[Route('/about-us', name: 'page_about_us')]
    public function aboutUs(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = $request->get('_locale') ?? $request->getLocale();
        $content = $shopInfo?->getTranslatedField('aboutUs', $locale) ?? '';
        $title = $request->query->get('title') ?? 'About Us';

        return $this->renderLocalized('static_page.html.twig', [
            'title' => $title,
            'content' => $content,
            'shopInfo' => $shopInfo,
            'locale' => $locale,
            'categories' => $em->getRepository(Category::class)->findAllCategories(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
        ], $request);
    }

    #[Route('/how-to-order', name: 'page_how_to_order')]
    public function howToOrder(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = $request->get('_locale') ?? $request->getLocale();
        $content = $shopInfo?->getTranslatedField('howToOrder', $locale) ?? '';
        $title = $request->query->get('title') ?? 'How To Order';

        return $this->renderLocalized('static_page.html.twig', [
            'title' => $title,
            'content' => $content,
            'shopInfo' => $shopInfo,
            'locale' => $locale,
            'categories' => $em->getRepository(Category::class)->findAllCategories(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
        ], $request);
    }

    #[Route('/business-conditions', name: 'page_business_conditions')]
    public function businessConditions(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = $request->get('_locale') ?? $request->getLocale();
        $content = $shopInfo?->getTranslatedField('businessConditions', $locale) ?? '';
        $title = $request->query->get('title') ?? 'Business Conditions';

        return $this->renderLocalized('static_page.html.twig', [
            'title' => $title,
            'content' => $content,
            'shopInfo' => $shopInfo,
            'locale' => $locale,
            'categories' => $em->getRepository(Category::class)->findAllCategories(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
        ], $request);
    }

    #[Route('/privacy-policy', name: 'page_privacy_policy')]
    public function privacyPolicy(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = $request->get('_locale') ?? $request->getLocale();
        $content = $shopInfo?->getTranslatedField('privacyPolicy', $locale) ?? '';
        $title = $request->query->get('title') ?? 'Privacy Policy';

        return $this->renderLocalized('static_page.html.twig', [
            'title' => $title,
            'content' => $content,
            'shopInfo' => $shopInfo,
            'locale' => $locale,
            'categories' => $em->getRepository(Category::class)->findAllCategories(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
        ], $request);
    }

    #[Route('/shipping-info', name: 'page_shipping_info')]
    public function shippingInfo(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = $request->get('_locale') ?? $request->getLocale();
        $content = $shopInfo?->getTranslatedField('shippingInfo', $locale) ?? '';
        $title = $request->query->get('title') ?? 'Shipping Info';

        return $this->renderLocalized('static_page.html.twig', [
            'title' => $title,
            'content' => $content,
            'shopInfo' => $shopInfo,
            'locale' => $locale,
            'categories' => $em->getRepository(Category::class)->findAllCategories(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
        ], $request);
    }

    #[Route('/payment', name: 'page_payment')]
    public function payment(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = $request->get('_locale') ?? $request->getLocale();
        $content = $shopInfo?->getTranslatedField('payment', $locale) ?? '';
        $title = $request->query->get('title') ?? 'Payment';

        return $this->renderLocalized('static_page.html.twig', [
            'title' => $title,
            'content' => $content,
            'shopInfo' => $shopInfo,
            'locale' => $locale,
            'categories' => $em->getRepository(Category::class)->findAllCategories(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
        ], $request);
    }

    #[Route('/refund', name: 'page_refund')]
    public function refund(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = $request->get('_locale') ?? $request->getLocale();
        $content = $shopInfo?->getTranslatedField('refund', $locale) ?? '';
        $title = $request->query->get('title') ?? 'Refund';

        return $this->renderLocalized('static_page.html.twig', [
            'title' => $title,
            'content' => $content,
            'shopInfo' => $shopInfo,
            'locale' => $locale,
            'categories' => $em->getRepository(Category::class)->findAllCategories(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
        ], $request);
    }
}