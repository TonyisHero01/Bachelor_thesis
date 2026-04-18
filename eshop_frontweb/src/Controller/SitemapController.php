<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SitemapController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $host = $request->getSchemeAndHttpHost();
        $locale = (string) $request->getLocale();

        $shopInfo = $this->entityManager
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $productRepo = $this->entityManager->getRepository(Product::class);

        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        $products = $productRepo->findAll();

        $urls = [];

        // 首页
        $urls[] = [
            'loc' => $host . $this->generateUrl('app_eshop_home', ['_locale' => $locale]),
            'lastmod' => null,
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        // 商品总览页
        $urls[] = [
            'loc' => $host . $this->generateUrl('app_eshop_product', ['_locale' => $locale]),
            'lastmod' => null,
            'changefreq' => 'daily',
            'priority' => '0.8',
        ];

        // 分类页
        foreach ($categories as $category) {
            $urls[] = [
                'loc' => $host . $this->generateUrl('app_eshop_category', [
                    'id' => $category->getId(),
                    '_locale' => $locale,
                ]),
                'lastmod' => null,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        // 商品详情页
        foreach ($products as $product) {
            $lastmod = null;

            if (method_exists($product, 'getUpdatedAt') && $product->getUpdatedAt() !== null) {
                $lastmod = $product->getUpdatedAt()->format('Y-m-d');
            } elseif (method_exists($product, 'getCreatedAt') && $product->getCreatedAt() !== null) {
                $lastmod = $product->getCreatedAt()->format('Y-m-d');
            }

            $urls[] = [
                'loc' => $host . $this->generateUrl('show_eshop_product', [
                    'id' => $product->getId(),
                    '_locale' => $locale,
                ]),
                'lastmod' => $lastmod,
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ];
        }

        $xml = $this->renderView('sitemap/sitemap.xml.twig', [
            'urls' => $urls,
            'shopInfo' => $shopInfo,
        ]);

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}