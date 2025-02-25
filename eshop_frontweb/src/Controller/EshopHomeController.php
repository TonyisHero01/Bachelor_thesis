<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Category;
use App\Entity\Product;
use App\Repository\ProductRepository;
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

        return $this->render('eshop/index.html.twig', [
            'show_sidebar' => false,
            'shopInfo' => $this->shopInfo,
            'new_products' => $new_products,
            'categories' => $categories
        ]);
    }

    #[Route('/category/{category}', name: 'app_eshop_category')]
    public function showCategory($category, ProductRepository $productRepository): Response
    {
        if (!$category) {
            throw $this->createNotFoundException('The category does not exist');
        }

        // 获取该分类的所有商品
        $products = $productRepository->findByCategoryName($category);

        // **先过滤掉 hidden = true 的商品**
        $filteredProducts = array_filter($products, function ($product) {
            return !$product->getHidden(); // 只保留 hidden = false 的商品
        });

        // **按照 ID 倒序排序**
        usort($filteredProducts, function ($a, $b) {
            return $b->getId() <=> $a->getId(); // 按 ID 从大到小排序
        });

        // **去重 SKU 并确保商品有图片**
        $seenSkus = [];
        $productsWithImages = [];
        foreach ($filteredProducts as $product) {
            if (!empty($product->getImageUrls()) && !in_array($product->getSku(), $seenSkus)) {
                $seenSkus[] = $product->getSku();
                $productsWithImages[] = $product;
            }
        }

        return $this->render('eshop/products.html.twig', [
            'show_sidebar' => true,
            'products' => $productsWithImages,
            'category' => $category,
            'shopInfo' => $this->shopInfo,
        ]);
    }
}



    
