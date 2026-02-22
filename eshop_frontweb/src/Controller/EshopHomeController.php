<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ShopInfo;
use App\Repository\ColorRepository;
use App\Repository\ProductRepository;
use App\Repository\ShopInfoRepository;
use App\Repository\SizeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class EshopHomeController extends BaseController
{
    protected ?ShopInfo $shopInfo = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        Environment $twig,
        LoggerInterface $logger,
        ManagerRegistry $doctrine,
    ) {
        parent::__construct($twig, $logger, $doctrine);

        $this->shopInfo = $this->entityManager
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Displays the e-shop homepage with categories and product highlights.
     */
    #[Route('/homepage', name: 'app_eshop_home', methods: ['GET'])]
    public function index(Request $request, ShopInfoRepository $shopInfoRepository): Response
    {
        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $productsRepo = $this->entityManager->getRepository(Product::class);

        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        $newProducts = method_exists($productsRepo, 'findLastFourProducts')
            ? $productsRepo->findLastFourProducts()
            : [];

        $popularProducts = method_exists($productsRepo, 'findTopSellingProducts')
            ? $productsRepo->findTopSellingProducts(10)
            : [];

        $shopInfoWithI18n = $shopInfoRepository->findWithTranslations();

        $locale = (string) ($request->get('_locale') ?? $request->getLocale());

        return $this->renderLocalized(
            'eshop/index.html.twig',
            [
                'show_sidebar' => false,
                'shopInfo' => $shopInfoWithI18n ?? $this->shopInfo,
                'locale' => $locale,
                'new_products' => $newProducts,
                'popular_products' => $popularProducts,
                'categories' => $categories,
            ],
            $request,
        );
    }

    /**
     * Displays a category page with products filtered to visible items with images,
     * keeping only the latest version per SKU.
     */
    #[Route('/category/{id}', name: 'app_eshop_category', methods: ['GET'])]
    public function showCategory(
        Request $request,
        Category $category,
        ProductRepository $productRepository,
        ColorRepository $colorRepository,
        SizeRepository $sizeRepository,
    ): Response {
        $locale = (string) $request->getLocale();

        $categoryName = (string) ($category->getTranslatedName($locale) ?? $category->getName() ?? '');

        $products = $productRepository->findLatestByCategory($category);

        $productsWithImages = array_values(array_filter(
            $products,
            static fn (Product $p): bool => !$p->getHidden() && !empty($p->getImageUrls())
        ));

        $translations = $this->getTranslations($request);
        $translations['base_template'] = 'eshop_base.html.twig';

        $localizedBasePath = sprintf('locale/%s/eshop_base.html.twig', $locale);
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $localizedBaseFullPath = $projectDir . '/templates/' . $localizedBasePath;

        if (is_file($localizedBaseFullPath)) {
            $translations['base_template'] = $localizedBasePath;
        }

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop/products.html.twig',
            [
                'category' => $categoryName,
                'products' => $productsWithImages,
                'colors' => $colorRepository->findAll(),
                'sizes' => $sizeRepository->findAll(),
                'categories' => $categories,
                'shopInfo' => $this->shopInfo,
                'locale' => $locale,
                'show_sidebar' => false,
                'translations' => $translations,
            ],
            $request,
        );
    }
}