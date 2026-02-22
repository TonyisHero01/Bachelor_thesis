<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StaticPageController extends BaseController
{
    /**
     * Loads the latest ShopInfo record with translations (if available).
     */
    private function getShopInfo(EntityManagerInterface $em): ?ShopInfo
    {
        $repo = $em->getRepository(ShopInfo::class);

        if (method_exists($repo, 'findWithTranslations')) {
            /** @var ShopInfo|null $shopInfo */
            $shopInfo = $repo->findWithTranslations();
            return $shopInfo;
        }

        /** @var ShopInfo|null $shopInfo */
        $shopInfo = $repo->findOneBy([], ['id' => 'DESC']);

        return $shopInfo;
    }

    /**
     * Renders the "About Us" static page.
     */
    #[Route('/about-us', name: 'page_about_us', methods: ['GET'])]
    public function aboutUs(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = (string) ($request->get('_locale') ?? $request->getLocale());
        $content = $shopInfo?->getTranslatedField('aboutUs', $locale) ?? '';
        $title = (string) ($request->query->get('title') ?? 'About Us');

        $categoriesRepo = $em->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'static_page.html.twig',
            [
                'title' => $title,
                'content' => $content,
                'shopInfo' => $shopInfo,
                'locale' => $locale,
                'categories' => $categories,
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
            ],
            $request,
        );
    }

    /**
     * Renders the "How To Order" static page.
     */
    #[Route('/how-to-order', name: 'page_how_to_order', methods: ['GET'])]
    public function howToOrder(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = (string) ($request->get('_locale') ?? $request->getLocale());
        $content = $shopInfo?->getTranslatedField('howToOrder', $locale) ?? '';
        $title = (string) ($request->query->get('title') ?? 'How To Order');

        $categoriesRepo = $em->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'static_page.html.twig',
            [
                'title' => $title,
                'content' => $content,
                'shopInfo' => $shopInfo,
                'locale' => $locale,
                'categories' => $categories,
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
            ],
            $request,
        );
    }

    /**
     * Renders the "Business Conditions" static page.
     */
    #[Route('/business-conditions', name: 'page_business_conditions', methods: ['GET'])]
    public function businessConditions(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = (string) ($request->get('_locale') ?? $request->getLocale());
        $content = $shopInfo?->getTranslatedField('businessConditions', $locale) ?? '';
        $title = (string) ($request->query->get('title') ?? 'Business Conditions');

        $categoriesRepo = $em->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'static_page.html.twig',
            [
                'title' => $title,
                'content' => $content,
                'shopInfo' => $shopInfo,
                'locale' => $locale,
                'categories' => $categories,
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
            ],
            $request,
        );
    }

    /**
     * Renders the "Privacy Policy" static page.
     */
    #[Route('/privacy-policy', name: 'page_privacy_policy', methods: ['GET'])]
    public function privacyPolicy(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = (string) ($request->get('_locale') ?? $request->getLocale());
        $content = $shopInfo?->getTranslatedField('privacyPolicy', $locale) ?? '';
        $title = (string) ($request->query->get('title') ?? 'Privacy Policy');

        $categoriesRepo = $em->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'static_page.html.twig',
            [
                'title' => $title,
                'content' => $content,
                'shopInfo' => $shopInfo,
                'locale' => $locale,
                'categories' => $categories,
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
            ],
            $request,
        );
    }

    /**
     * Renders the "Shipping Info" static page.
     */
    #[Route('/shipping-info', name: 'page_shipping_info', methods: ['GET'])]
    public function shippingInfo(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = (string) ($request->get('_locale') ?? $request->getLocale());
        $content = $shopInfo?->getTranslatedField('shippingInfo', $locale) ?? '';
        $title = (string) ($request->query->get('title') ?? 'Shipping Info');

        $categoriesRepo = $em->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'static_page.html.twig',
            [
                'title' => $title,
                'content' => $content,
                'shopInfo' => $shopInfo,
                'locale' => $locale,
                'categories' => $categories,
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
            ],
            $request,
        );
    }

    /**
     * Renders the "Payment" static page.
     */
    #[Route('/payment', name: 'page_payment', methods: ['GET'])]
    public function payment(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = (string) ($request->get('_locale') ?? $request->getLocale());
        $content = $shopInfo?->getTranslatedField('payment', $locale) ?? '';
        $title = (string) ($request->query->get('title') ?? 'Payment');

        $categoriesRepo = $em->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'static_page.html.twig',
            [
                'title' => $title,
                'content' => $content,
                'shopInfo' => $shopInfo,
                'locale' => $locale,
                'categories' => $categories,
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
            ],
            $request,
        );
    }

    /**
     * Renders the "Refund" static page.
     */
    #[Route('/refund', name: 'page_refund', methods: ['GET'])]
    public function refund(EntityManagerInterface $em, Request $request): Response
    {
        $shopInfo = $this->getShopInfo($em);
        $locale = (string) ($request->get('_locale') ?? $request->getLocale());
        $content = $shopInfo?->getTranslatedField('refund', $locale) ?? '';
        $title = (string) ($request->query->get('title') ?? 'Refund');

        $categoriesRepo = $em->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'static_page.html.twig',
            [
                'title' => $title,
                'content' => $content,
                'shopInfo' => $shopInfo,
                'locale' => $locale,
                'categories' => $categories,
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
            ],
            $request,
        );
    }
}