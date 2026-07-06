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
use App\Service\RecommendationEventLogger;

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
        HttpClientInterface $httpClient,
        RecommendationEventLogger $recommendationEventLogger
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

        if ($popularProducts === []) {
            $popularProducts = $this->findFallbackRandomProducts(10);
        }

        $shopInfoWithI18n = $shopInfoRepository->findWithTranslations();

        $searchConfig = $this->entityManager
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        $recommendationsEnabled = $searchConfig?->isRecommendationEnabled() ?? true;
        $recommendationLoggingEnabled = $searchConfig?->isRecommendationLoggingEnabled() ?? true;

        $recommendedForYou = [];

        if ($recommendationsEnabled) {
            $recommendedForYou = $this->buildRecommendedForYou($request, $httpClient, 10);

            if ($recommendationLoggingEnabled) {
                $recommendationEventLogger->logManyImpressions(
                    pageType: 'homepage',
                    sourceSku: null,
                    recommendations: $recommendedForYou,
                    algorithm: $this->getUser() instanceof Customer ? 'personalized_homepage' : 'global_homepage'
                );
            }
        }

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
                'recommendations_enabled' => $recommendationsEnabled,
                'recommendation_logging_enabled' => $recommendationLoggingEnabled,
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

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $products = $productRepository->findLatestByCategoryPaged(
            $category,
            $limit,
            $offset
        );

        $totalProducts = $productRepository->countLatestByCategory($category);
        $totalPages = max(1, (int) ceil($totalProducts / $limit));

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
                'products' => $products,
                'colors' => $colorRepository->findAll(),
                'sizes' => $sizeRepository->findAll(),
                'categories' => $categories,
                'shopInfo' => $this->shopInfo,
                'locale' => $locale,
                'show_sidebar' => false,
                'translations' => $translations,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalProducts' => $totalProducts,
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
            ->findOneBy(
                ['active' => true],
                ['id' => 'DESC']
            );

        $wishlistWeight =
            $config?->getWishlistRecommendationWeight()
            ?? 0.35;

        $orderWeight =
            $config?->getOrderHistoryRecommendationWeight()
            ?? 0.30;

        $searchWeight =
            $config?->getSearchHistoryRecommendationWeight()
            ?? 0.15;

        $maxRecommendationPerCategory =
            $config?->getMaxRecommendationPerCategory()
            ?? 4;

        $recommendationDiversityPenalty =
            $config?->getRecommendationDiversityPenalty()
            ?? 0.10;

        if ($customer instanceof Customer) {
            $this->addWishlistScores($scores, $customer, $httpClient, $wishlistWeight);
            $this->addOrderHistoryScores($scores, $customer, $httpClient, $orderWeight);
            $this->addCustomerSearchHistoryScores(
                $scores,
                $customer,
                $httpClient,
                $searchWeight,
                3
            );

            if ($scores === []) {
                $this->addGlobalPopularSearchScores(
                    $scores,
                    $httpClient,
                    $searchWeight,
                    3
                );
                $this->addGlobalPopularOrderScores($scores, $httpClient, $orderWeight);
                $this->addGlobalWishlistScores($scores, $httpClient, $wishlistWeight);
            }
        }
        else {
            $this->addGlobalPopularSearchScores(
                $scores,
                $httpClient,
                $searchWeight,
                3
            );
            $this->addGlobalPopularOrderScores($scores, $httpClient, $orderWeight);
            $this->addGlobalWishlistScores($scores, $httpClient, $wishlistWeight);
        }

        if ($scores === []) {
            return [];
        }

        $scores = $this->applyRecommendationDiversity(
            $scores,
            $maxRecommendationPerCategory,
            $recommendationDiversityPenalty
        );

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
    ): void {
        $wishlistIds = $customer->getWishlist();

        if ($wishlistIds === []) {
            return;
        }

        $products = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $wishlistIds]);

        $seedWeights = [];

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $sku = trim((string) $product->getSku());

            if ($sku === '') {
                continue;
            }

            $seedWeights[$sku] = max($seedWeights[$sku] ?? 0.0, $weight);
        }

        $this->addRecommendedBatchSkuScores(
            $scores,
            $seedWeights,
            $httpClient,
            20
        );
    }

    private function addOrderHistoryScores(
        array &$scores,
        Customer $customer,
        HttpClientInterface $httpClient,
        float $weight
    ): void {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('oi.sku AS sku')
            ->from(OrderItem::class, 'oi')
            ->join('oi.order', 'o')
            ->where('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('o.orderCreatedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        $seedWeights = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku === '') {
                continue;
            }

            $seedWeights[$sku] = max($seedWeights[$sku] ?? 0.0, $weight);
        }

        $this->addRecommendedBatchSkuScores(
            $scores,
            $seedWeights,
            $httpClient,
            20
        );
    }

    private function addCustomerSearchHistoryScores(
        array &$scores,
        Customer $customer,
        HttpClientInterface $httpClient,
        float $weight,
        int $maxQueries = 3
    ): void {

        $logs = $this->entityManager->createQueryBuilder()
            ->select('l.query AS query')
            ->from(CustomerSearchLog::class, 'l')
            ->where('l.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($maxQueries)
            ->getQuery()
            ->getArrayResult();

        $usedQueries = [];

        foreach ($logs as $log) {

            $query = trim((string) ($log['query'] ?? ''));

            if ($query === '') {
                continue;
            }

            $queryKey = mb_strtolower($query);

            if (isset($usedQueries[$queryKey])) {
                continue;
            }

            $usedQueries[$queryKey] = true;

            $results = $this->callSearchServiceSearch(
                $query,
                5,
                $httpClient
            );

            foreach ($results as $index => $item) {

                if (!is_array($item)) {
                    continue;
                }

                $sku = $this->getSkuFromSearchServiceRow($item);

                if ($sku === '') {
                    continue;
                }

                $similarity = (float) ($item['similarity'] ?? 1.0);
                $positionWeight = 1.0 / ($index + 1);

                $scores[$sku] = ($scores[$sku] ?? 0.0)
                    + ($similarity * $positionWeight * $weight);
            }
        }
    }

    private function addRecommendedSkuScores(
        array &$scores,
        string $sku,
        float $weight,
        HttpClientInterface $httpClient
    ): void {
        $results = $this->callSearchServiceRecommend($sku, 5, $httpClient);

        foreach ($results as $item) {
            if (!is_array($item)) {
                continue;
            }

            $recommendedSku = $this->getSkuFromSearchServiceRow($item);
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

    private function addRecommendedBatchSkuScores(
        array &$scores,
        array $seedWeights,
        HttpClientInterface $httpClient,
        int $limit = 20
    ): void {
        $results = $this->callSearchServiceRecommendBatch(
            $seedWeights,
            $limit,
            $httpClient
        );

        foreach ($results as $item) {
            if (!is_array($item)) {
                continue;
            }

            $recommendedSku = $this->getSkuFromSearchServiceRow($item);
            $similarity = (float) ($item['similarity'] ?? 0);

            if ($recommendedSku !== '' && $similarity > 0) {
                $scores[$recommendedSku] = ($scores[$recommendedSku] ?? 0.0) + $similarity;
            }
        }
    }

    private function callSearchServiceRecommendBatch(
        array $seedWeights,
        int $limit,
        HttpClientInterface $httpClient
    ): array {
        $seeds = [];

        foreach ($seedWeights as $sku => $weight) {
            $sku = trim((string) $sku);
            $weight = (float) $weight;

            if ($sku === '' || $weight <= 0) {
                continue;
            }

            $seeds[] = [
                'sku' => $sku,
                'weight' => $weight,
            ];
        }

        if ($seeds === []) {
            return [];
        }

        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

            $response = $httpClient->request('POST', $baseUrl . '/recommend/batch', [
                'json' => [
                    'seeds' => $seeds,
                    'limit' => $limit,
                ],
                'timeout' => 2.0,
                'max_duration' => 3.0,
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
                'headers' => [
                    'X-BENCHMARK' => '1',
                ],
                'json' => [
                    'query' => $query,
                    'limit' => $limit,
                ],
                'timeout' => 2.0,
                'max_duration' => 3.0,
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
        float $weight,
        int $maxQueries = 3
    ): void {

        $logs = $this->entityManager->createQueryBuilder()
            ->select('l.query AS query')
            ->from(CustomerSearchLog::class, 'l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($maxQueries)
            ->getQuery()
            ->getArrayResult();

        $usedQueries = [];

        foreach ($logs as $log) {

            $query = trim((string) ($log['query'] ?? ''));

            if ($query === '') {
                continue;
            }

            $queryKey = mb_strtolower($query);

            if (isset($usedQueries[$queryKey])) {
                continue;
            }

            $usedQueries[$queryKey] = true;

            $results = $this->callSearchServiceSearch(
                $query,
                5,
                $httpClient
            );

            foreach ($results as $index => $item) {

                if (!is_array($item)) {
                    continue;
                }

                $sku = $this->getSkuFromSearchServiceRow($item);

                if ($sku === '') {
                    continue;
                }

                $similarity = (float) ($item['similarity'] ?? 1.0);
                $positionWeight = 1.0 / ($index + 1);

                $scores[$sku] = ($scores[$sku] ?? 0.0)
                    + ($similarity * $positionWeight * $weight);
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
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();

        $seedWeights = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku === '') {
                continue;
            }

            $seedWeights[$sku] = max($seedWeights[$sku] ?? 0.0, $weight);
        }

        $this->addRecommendedBatchSkuScores(
            $scores,
            $seedWeights,
            $httpClient,
            20
        );
    }

    private function addGlobalWishlistScores(
        array &$scores,
        HttpClientInterface $httpClient,
        float $weight
    ): void {
        $customers = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

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

        if ($wishlistCount === []) {
            return;
        }

        arsort($wishlistCount);

        $topProductIds = array_slice(
            array_keys($wishlistCount),
            0,
            10
        );

        $products = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $topProductIds]);

        $seedWeights = [];

        foreach ($products as $product) {
            if (!$product instanceof Product) {
                continue;
            }

            $sku = trim((string) $product->getSku());

            if ($sku === '') {
                continue;
            }

            $seedWeights[$sku] = max($seedWeights[$sku] ?? 0.0, $weight);
        }

        $this->addRecommendedBatchSkuScores(
            $scores,
            $seedWeights,
            $httpClient,
            20
        );
    }

    private function findFallbackRandomProducts(int $limit = 10): array
    {
        $products = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.hidden = false')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        $withImages = array_values(array_filter(
            $products,
            static fn (Product $product): bool => !empty($product->getImageUrls())
        ));

        if ($withImages === []) {
            $withImages = $products;
        }

        shuffle($withImages);

        return array_slice($withImages, 0, $limit);
    }

    private function getSkuFromSearchServiceRow(array $row): string
    {
        if (isset($row['product_sku'])) {
            return trim((string) $row['product_sku']);
        }

        if (isset($row['sku'])) {
            return trim((string) $row['sku']);
        }

        return '';
    }

    private function applyRecommendationDiversity(
        array $scores,
        int $maxPerCategory,
        float $penalty
    ): array {
        if ($scores === []) {
            return [];
        }

        arsort($scores);

        $skus = array_keys($scores);

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.sku AS sku')
            ->addSelect('c.name AS category')
            ->from(Product::class, 'p')
            ->leftJoin('p.category', 'c')
            ->where('p.sku IN (:skus)')
            ->andWhere('p.hidden = false')
            ->setParameter('skus', $skus)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $skuToCategory = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku === '' || isset($skuToCategory[$sku])) {
                continue;
            }

            $skuToCategory[$sku] = (string) ($row['category'] ?? 'unknown');
        }

        $categoryCount = [];
        $adjusted = [];

        foreach ($scores as $sku => $score) {
            $category = $skuToCategory[$sku] ?? 'unknown';
            $currentCount = $categoryCount[$category] ?? 0;

            if ($currentCount >= $maxPerCategory) {
                $overflow = $currentCount - $maxPerCategory + 1;
                $score -= $penalty * $overflow;
            }

            if ($score <= 0) {
                continue;
            }

            if ($currentCount >= $maxPerCategory * 2) {
                continue;
            }

            $adjusted[$sku] = $score;
            $categoryCount[$category] = $currentCount + 1;
        }

        return $adjusted;
    }
}