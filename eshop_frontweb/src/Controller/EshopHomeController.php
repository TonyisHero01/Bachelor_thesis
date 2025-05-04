<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Size;
use App\Repository\ProductRepository;
use App\Repository\ColorRepository;
use App\Repository\SizeRepository;
use App\Repository\ShopInfoRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Controller\BaseController;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class EshopHomeController extends BaseController
{
    public $shopInfo;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, Environment $twig, LoggerInterface $logger)
    {
        parent::__construct($twig, $logger);
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/homepage', name: 'app_eshop_home')]
    public function index(Request $request, ShopInfoRepository $shopInfoRepository): Response
    {
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        $new_products = $this->entityManager->getRepository(Product::class)->findLastFourProducts();
        $popularProducts = $this->entityManager->getRepository(Product::class)->findTopSellingProducts(10);

        $shopInfo = $shopInfoRepository->findWithTranslations();
        $locale = $request->get('_locale') ?? $request->getLocale();

        return $this->renderLocalized('eshop/index.html.twig', [
            'show_sidebar' => false,
            'shopInfo' => $shopInfo,
            'locale' => $locale,
            'languages' => $this->getAvailableLanguages(),
            'new_products' => $new_products,
            'popular_products' => $popularProducts,
            'categories' => $categories
        ], $request);
    }

    #[Route('/category/{id}', name: 'app_eshop_category')]
    public function showCategory(
        Request $request,
        Category $category,
        ProductRepository $productRepository,
        ColorRepository $colorRepository,
        SizeRepository $sizeRepository
    ): Response {
        $categoryName = $category->getTranslatedName($request->getLocale());
        $allProducts = $productRepository->findBy(['category' => $category]);

        $filtered = array_filter($allProducts, fn($p) => !$p->getHidden() && !empty($p->getImageUrls()));
        usort($filtered, fn($a, $b) => $b->getId() <=> $a->getId());

        $seenSkus = [];
        $productsWithImages = [];
        foreach ($filtered as $product) {
            if (!in_array($product->getSku(), $seenSkus)) {
                $seenSkus[] = $product->getSku();
                $productsWithImages[] = $product;
            }
        }

        $translations = $this->getTranslations($request);
        $translations['base_template'] = 'eshop_base.html.twig';

        // 如果存在 localized 的 base 模板，使用它
        $locale = $request->getLocale();
        $localizedBasePath = "locale/{$locale}/eshop_base.html.twig";
        $projectDir = $this->getParameter('kernel.project_dir');
        $localizedBaseFullPath = $projectDir . "/templates/{$localizedBasePath}";
        if (file_exists($localizedBaseFullPath)) {
            $translations['base_template'] = $localizedBasePath;
        }

        return $this->renderLocalized('eshop/products.html.twig', [
            'category' => $categoryName,
            'products' => $productsWithImages,
            'colors' => $colorRepository->findAll(),
            'sizes' => $sizeRepository->findAll(),
            'categories' => $this->entityManager->getRepository(Category::class)->findAllCategories(),
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => true,
            'translations' => $translations,
        ], $request);
    }


}