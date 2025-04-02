<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Size;
use App\Repository\ProductRepository;
use App\Repository\ColorRepository;
use App\Repository\SizeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class EshopHomeController extends AbstractController
{
    public $shopInfo;
    private $entityManager;

    // 构造函数中注入 EntityManagerInterface，并加载 shopInfo
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/homepage', name: 'app_eshop_home')]
    public function index(): Response
    {
        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        $new_products = $this->entityManager->getRepository(Product::class)->findLastFourProducts();
        $popularProducts = $this->entityManager->getRepository(Product::class)->findTopSellingProducts(10);

        return $this->render('eshop/index.html.twig', [
            'show_sidebar' => false,
            'shopInfo' => $this->shopInfo,
            'new_products' => $new_products,
            'popular_products' => $popularProducts,
            'categories' => $categories
        ]);
    }

    #[Route('/category/{id}', name: 'app_eshop_category')]
    public function showCategory(
        Category $category,
        ProductRepository $productRepository,
        ColorRepository $colorRepository,
        SizeRepository $sizeRepository
    ): Response {
        $categoryName = $category->getName(); // 注意这里改成字符串

        $allProducts = $productRepository->findBy(['category' => $categoryName]);

        // 过滤 + 排序 + 去重
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

        return $this->render('eshop/products.html.twig', [
            'category' => $categoryName,
            'products' => $productsWithImages,
            'colors' => $colorRepository->findAll(),
            'sizes' => $sizeRepository->findAll(),
            'categories' => $this->entityManager->getRepository(Category::class)->findAllCategories(),
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => true,
        ]);
    }
}



    
