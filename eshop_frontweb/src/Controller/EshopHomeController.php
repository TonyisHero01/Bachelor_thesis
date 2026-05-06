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
use App\Entity\Customer;
use App\Entity\CustomerSearchLog;
use App\Entity\OrderItem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\SearchRelevanceConfig;

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
    public function index(
        Request $request,
        ShopInfoRepository $shopInfoRepository,
        HttpClientInterface $httpClient
    ): Response
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
        $recommendedForYou = $this->buildRecommendedForYou($request, $httpClient, 10);

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
                'recommended_for_you' => $recommendedForYou,
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

    private function buildRecommendedForYou(
        Request $request,
        HttpClientInterface $httpClient,
        int $limit = 10
    ): array {
        $scores = [];

        $user = $this->getUser();
        $customer = $user instanceof Customer ? $user : null;

        $config = $this->entityManager
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        $wishlistWeight = $config?->getWishlistRecommendationWeight() ?? 0.30;
        $orderWeight = $config?->getOrderHistoryRecommendationWeight() ?? 0.25;
        $searchWeight = $config?->getSearchHistoryRecommendationWeight() ?? 0.20;

        // =========================
        // LOGGED USER
        // =========================

        if ($customer instanceof Customer) {
            $this->addWishlistScores($scores, $customer, $httpClient, $wishlistWeight);
            $this->addOrderHistoryScores($scores, $customer, $httpClient, $orderWeight);
            $this->addCustomerSearchHistoryScores($scores, $customer, $httpClient, $searchWeight);

            // If the logged-in customer has no usable behavior yet,
            // fall back to global recommendation data.
            if ($scores === []) {
                $this->addGlobalPopularSearchScores($scores, $httpClient, $searchWeight);
                $this->addGlobalPopularOrderScores($scores, $httpClient, $orderWeight);
                $this->addGlobalWishlistScores($scores, $httpClient, $wishlistWeight);
            }
        }

        // =========================
        // GUEST USER
        // =========================

        else {
            $this->addGlobalPopularSearchScores($scores, $httpClient, $searchWeight);
            $this->addGlobalPopularOrderScores($scores, $httpClient, $orderWeight);
            $this->addGlobalWishlistScores($scores, $httpClient, $wishlistWeight);
        }

        if ($scores === []) {
            return [];
        }

        arsort($scores);

        $skus = array_slice(array_keys($scores), 0, $limit * 3);

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.sku IN (:skus)')
            ->andWhere('p.hidden = false')
            ->setParameter('skus', $skus)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        $skuToProduct = [];

        foreach ($rows as $product) {

            if (!$product instanceof Product) {
                continue;
            }

            $sku = (string) $product->getSku();

            if ($sku !== '' && !isset($skuToProduct[$sku])) {
                $skuToProduct[$sku] = $product;
            }
        }

        $products = [];

        foreach ($skus as $sku) {

            if (isset($skuToProduct[$sku])) {
                $products[] = $skuToProduct[$sku];
            }

            if (count($products) >= $limit) {
                break;
            }
        }

        return $products;
    }

    private function addWishlistScores(
        array &$scores,
        Customer $customer,
        HttpClientInterface $httpClient,
        float $weight
    ): void
    {
        $wishlistIds = $customer->getWishlist();

        if ($wishlistIds === []) {
            return;
        }

        $products = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $wishlistIds]);

        foreach ($products as $product) {
            if ($product instanceof Product && $product->getSku()) {
                $this->addRecommendedSkuScores(
                    $scores,
                    (string) $product->getSku(),
                    $weight,
                    $httpClient
                );
            }
        }
    }

    private function addOrderHistoryScores(
        array &$scores,
        Customer $customer,
        HttpClientInterface $httpClient,
        float $weight
    ): void
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('oi.sku AS sku')
            ->from(OrderItem::class, 'oi')
            ->join('oi.order', 'o')
            ->where('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('o.orderCreatedAt', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku !== '') {
                $this->addRecommendedSkuScores($scores, $sku, $weight, $httpClient);
            }
        }
    }

    private function addCustomerSearchHistoryScores(
        array &$scores,
        Customer $customer,
        HttpClientInterface $httpClient,
        float $weight
    ): void {

        $logs = $this->entityManager->createQueryBuilder()
            ->select('l.query AS query')
            ->from(CustomerSearchLog::class, 'l')
            ->where('l.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getArrayResult();

        foreach ($logs as $log) {

            $query = trim((string) ($log['query'] ?? ''));

            if ($query === '') {
                continue;
            }

            $results = $this->callSearchServiceSearch(
                $query,
                10,
                $httpClient
            );

            foreach ($results as $item) {

                $sku = trim((string) ($item['product_sku'] ?? ''));
                $similarity = (float) ($item['similarity'] ?? 0);

                if ($sku === '' || $similarity <= 0) {
                    continue;
                }

                $scores[$sku] = ($scores[$sku] ?? 0)
                    + ($similarity * $weight);
            }
        }
    }

    private function addRecommendedSkuScores(
        array &$scores,
        string $sku,
        float $weight,
        HttpClientInterface $httpClient
    ): void {
        $results = $this->callSearchServiceRecommend($sku, 10, $httpClient);

        foreach ($results as $item) {
            $recommendedSku = trim((string) ($item['product_sku'] ?? ''));
            $similarity = (float) ($item['similarity'] ?? 0);

            if ($recommendedSku !== '' && $similarity > 0) {
                $scores[$recommendedSku] = ($scores[$recommendedSku] ?? 0) + ($similarity * $weight);
            }
        }
    }

    private function callSearchServiceRecommend(
        string $sku,
        int $limit,
        HttpClientInterface $httpClient
    ): array {
        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

            $response = $httpClient->request('GET', $baseUrl . '/recommend/' . rawurlencode($sku), [
                'query' => ['limit' => $limit],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() >= 400) {
                return [];
            }

            $data = $response->toArray(false);

            return is_array($data['results'] ?? null) ? $data['results'] : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function callSearchServiceSearch(
        string $query,
        int $limit,
        HttpClientInterface $httpClient
    ): array {
        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

            $response = $httpClient->request('POST', $baseUrl . '/search', [
                'json' => [
                    'query' => $query,
                    'limit' => $limit,
                ],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() >= 400) {
                return [];
            }

            $data = $response->toArray(false);

            return is_array($data['results'] ?? null) ? $data['results'] : [];
        } catch (\Throwable) {
            return [];
        }
    }
    private function addGlobalPopularSearchScores(
        array &$scores,
        HttpClientInterface $httpClient,
        float $weight
    ): void {

        $logs = $this->entityManager->createQueryBuilder()
            ->select('l.query AS query')
            ->from(CustomerSearchLog::class, 'l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getArrayResult();

        foreach ($logs as $log) {

            $query = trim((string) ($log['query'] ?? ''));

            if ($query === '') {
                continue;
            }

            $results = $this->callSearchServiceSearch(
                $query,
                5,
                $httpClient
            );

            foreach ($results as $item) {

                $sku = trim((string) ($item['product_sku'] ?? ''));
                $similarity = (float) ($item['similarity'] ?? 0);

                if ($sku !== '' && $similarity > 0) {
                    $scores[$sku] = ($scores[$sku] ?? 0)
                        + ($similarity * $weight);
                }
            }
        }
    }

    private function addGlobalPopularOrderScores(
        array &$scores,
        HttpClientInterface $httpClient,
        float $weight
    ): void {

        $rows = $this->entityManager->createQueryBuilder()
            ->select('oi.sku AS sku, COUNT(oi.id) AS cnt')
            ->from(OrderItem::class, 'oi')
            ->groupBy('oi.sku')
            ->orderBy('cnt', 'DESC')
            ->setMaxResults(30)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {

            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku === '') {
                continue;
            }

            $this->addRecommendedSkuScores(
                $scores,
                $sku,
                $weight,
                $httpClient
            );
        }
    }

    private function addGlobalWishlistScores(
        array &$scores,
        HttpClientInterface $httpClient,
        float $weight
    ): void {

        $customers = $this->entityManager
            ->getRepository(Customer::class)
            ->findAll();

        $wishlistCount = [];

        foreach ($customers as $customer) {

            if (!$customer instanceof Customer) {
                continue;
            }

            foreach ($customer->getWishlist() as $productId) {

                $wishlistCount[$productId] =
                    ($wishlistCount[$productId] ?? 0) + 1;
            }
        }

        arsort($wishlistCount);

        $topProductIds = array_slice(
            array_keys($wishlistCount),
            0,
            20
        );

        $products = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $topProductIds]);

        foreach ($products as $product) {

            if (!$product instanceof Product || !$product->getSku()) {
                continue;
            }

            $this->addRecommendedSkuScores(
                $scores,
                (string) $product->getSku(),
                $weight,
                $httpClient
            );
        }
    }
}