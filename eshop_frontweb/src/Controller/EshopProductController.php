<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use App\Entity\CustomerSearchLog;
use App\Entity\Order;
use App\Entity\OrderItem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Entity\CustomerProductViewLog;
use App\Entity\SearchRelevanceConfig;
use App\Service\RecommendationEventLogger;

class EshopProductController extends BaseController
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
     * Displays the product overview entry page.
     */
    #[Route('/eshop/product', name: 'app_eshop_product', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        return $this->renderLocalized(
            'eshop_product/index.html.twig',
            [
                'shopInfo' => $this->shopInfo,
                'locale' => (string) $request->getLocale(),
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
                'categories' => $categories,
            ],
            $request,
        );
    }

    private function addRecommendedBatchSkuScores(
        array &$scores,
        array $seedWeights,
        HttpClientInterface $httpClient,
        int $limit = 20
    ): void {
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
            return;
        }

        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

            $response = $httpClient->request(
                'POST',
                $baseUrl . '/recommend/batch',
                [
                    'json' => [
                        'seeds' => $seeds,
                        'limit' => $limit,
                    ],
                    'timeout' => 2.0,
                    'max_duration' => 3.0,
                ]
            );

            if ($response->getStatusCode() >= 400) {
                return;
            }

            $data = $response->toArray(false);

            foreach (($data['results'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $recommendedSku = $this->getRecommendedSkuFromRow($row);
                $similarity = (float) ($row['similarity'] ?? 0);

                if ($recommendedSku === '' || $similarity <= 0) {
                    continue;
                }

                $scores[$recommendedSku] = ($scores[$recommendedSku] ?? 0.0) + $similarity;
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                '[Recommendation][Batch] Failed to fetch batch recommendations',
                [
                    'seed_count' => count($seeds),
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Displays product detail page for the given product id.
     */
    #[Route('/product/{id}', name: 'show_eshop_product', methods: ['GET'])]
    public function show(
        Request $request,
        int $id,
        HttpClientInterface $httpClient,
        RecommendationEventLogger $recommendationEventLogger
    ): Response
    {
        $productRepo = $this->entityManager->getRepository(Product::class);

        $product = method_exists($productRepo, 'findProductById')
            ? $productRepo->findProductById($id)
            : $productRepo->find($id);

        if (!$product instanceof Product) {
            throw $this->createNotFoundException(sprintf('No product found for id %d', $id));
        }

        $this->saveProductViewLog($request, $product);

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        $shopInfo = $this->entityManager->getRepository(ShopInfo::class)->findOneBy([]);

        $searchConfig = $this->entityManager
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        $recommendationsEnabled = $searchConfig?->isRecommendationEnabled() ?? true;
        $recommendationLoggingEnabled = $searchConfig?->isRecommendationLoggingEnabled() ?? true;

        $recommendedProducts = [];

        if ($recommendationsEnabled) {
            $recommendedProducts = $this->buildHybridRecommendations(
                $request,
                $product,
                $httpClient,
                5
            );

            if ($recommendationLoggingEnabled) {
                $recommendationEventLogger->logManyImpressions(
                    pageType: 'product_detail',
                    sourceSku: $product->getSku(),
                    recommendations: $recommendedProducts,
                    algorithm: 'hybrid'
                );
            }
        }

        return $this->renderLocalized(
            'eshop_product/index.html.twig',
            [
                'shopInfo' => $shopInfo,
                'locale' => (string) $request->getLocale(),
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
                'product' => $product,
                'BMS_URL' => $this->getParameter('BMS_URL'),
                'categories' => $categories,
                'recommendedProducts' => $recommendedProducts,
                'recommendations_enabled' => $recommendationsEnabled,
                'recommendation_logging_enabled' => $recommendationLoggingEnabled,
            ],
            $request,
        );
    }

    /**
     * Adds a product to the authenticated customer's cart.
     * If the cart item already exists, its quantity is increased.
     */
    #[Route('/cart/add', name: 'add_to_cart', methods: ['POST'])]
    public function addToCart(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON body'], 400);
        }

        $productId = (int) ($data['productId'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 1);

        if ($productId <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid productId'], 400);
        }

        if ($quantity <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid quantity'], 400);
        }

        $user = $this->getUser();
        if (!$user instanceof Customer) {
            return new JsonResponse(['success' => false, 'message' => 'User not authenticated'], 403);
        }

        $customer = $this->entityManager->getRepository(Customer::class)->find($user->getId());
        $product = $this->entityManager->getRepository(Product::class)->find($productId);

        if (!$customer instanceof Customer || !$product instanceof Product) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid product or user'], 400);
        }

        $cartRepo = $this->entityManager->getRepository(Cart::class);
        $cartItem = $cartRepo->findOneBy([
            'customer' => $customer,
            'product' => $product,
        ]);

        if ($cartItem instanceof Cart) {
            $cartItem->setQuantity($cartItem->getQuantity() + $quantity);
        } else {
            $cartItem = new Cart();
            $cartItem->setCustomer($customer);
            $cartItem->setProduct($product);
            $cartItem->setQuantity($quantity);
            $cartItem->setAddedAt(new \DateTime());
            $this->entityManager->persist($cartItem);
        }

        $this->entityManager->flush();

        $cartTotalQuantity = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(c.quantity), 0)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'success' => true,
            'message' => 'Product added to cart',
            'cartCount' => (int) $cartTotalQuantity,
        ]);
    }

    /**
     * Returns the total quantity of items in the authenticated customer's cart.
     */
    #[Route('/cart/count', name: 'cart_count', methods: ['GET'])]
    public function getCartCount(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Customer) {
            return new JsonResponse(['cartCount' => 0]);
        }

        $customer = $this->entityManager->getRepository(Customer::class)->find($user->getId());
        if (!$customer instanceof Customer) {
            return new JsonResponse(['cartCount' => 0]);
        }

        $cartTotalQuantity = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(c.quantity), 0)')
            ->from(Cart::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse(['cartCount' => (int) $cartTotalQuantity]);
    }

    private function getRecommendedSkuFromRow(array $row): string
    {
        if (isset($row['product_sku'])) {
            return trim((string) $row['product_sku']);
        }

        if (isset($row['sku'])) {
            return trim((string) $row['sku']);
        }

        return '';
    }

    private function buildHybridRecommendations(
        Request $request,
        Product $currentProduct,
        HttpClientInterface $httpClient,
        int $limit = 8,
        bool $includeSearchHistory = false
    ): array {
        $scores = [];

        $config = $this->entityManager
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(
                ['active' => true],
                ['id' => 'DESC']
            );

        $wishlistWeight =
            (float) ($config?->getWishlistRecommendationWeight() ?? 0.30);

        $orderWeight =
            (float) ($config?->getOrderHistoryRecommendationWeight() ?? 0.25);

        $searchWeight =
            (float) ($config?->getSearchHistoryRecommendationWeight() ?? 0.20);

        $viewWeight =
            (float) ($config?->getViewHistoryRecommendationWeight() ?? 0.35);

        $this->addSessionRecommendationScores(
            $scores,
            $request,
            $currentProduct,
            $httpClient,
            1.0
        );

        $this->addWishlistScores($scores, $request, $httpClient, $wishlistWeight);
        $this->addOrderHistoryScores($scores, $request, $httpClient, $orderWeight);
        if ($includeSearchHistory) {
            $this->addSearchHistoryScores(
                $scores,
                $request,
                $httpClient,
                $searchWeight,
                3
            );
        }
        //$this->addViewHistoryScores($scores, $request, $httpClient, $viewWeight);

        $currentSku = trim((string) $currentProduct->getSku());

        if ($currentSku !== '') {
            unset($scores[$currentSku]);
        }

        foreach ($this->getCurrentCartSkus() as $cartSku) {
            unset($scores[$cartSku]);
        }

        arsort($scores);

        $skus = array_slice(array_keys($scores), 0, $limit * 3);

        if ($skus === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where('p.sku IN (:skus)')
            ->andWhere('p.hidden = false')
            ->setParameter('skus', $skus)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $skuToLatestId = [];

        foreach ($rows as $row) {
            $sku = (string) ($row['sku'] ?? '');
            $id = (int) ($row['id'] ?? 0);

            if ($sku !== '' && $id > 0 && !isset($skuToLatestId[$sku])) {
                $skuToLatestId[$sku] = $id;
            }
        }

        $ids = [];

        foreach ($skus as $sku) {
            if (isset($skuToLatestId[$sku])) {
                $ids[] = $skuToLatestId[$sku];
            }
        }

        $ids = array_slice($ids, 0, $limit);

        if ($ids === []) {
            return [];
        }

        $productsRaw = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $ids]);

        $productMap = [];

        foreach ($productsRaw as $product) {
            if ($product instanceof Product) {
                $productMap[$product->getId()] = $product;
            }
        }

        $products = [];

        foreach ($ids as $productId) {
            if (isset($productMap[$productId])) {
                $products[] = $productMap[$productId];
            }
        }

        return $products;
    }

    private function addSessionRecommendationScores(
        array &$scores,
        Request $request,
        Product $currentProduct,
        HttpClientInterface $httpClient,
        float $weight = 1.0
    ): void {
        $currentSku = trim((string) $currentProduct->getSku());

        if ($currentSku === '') {
            return;
        }

        $viewedSkus = $this->getRecentViewedSkus($request, 5);
        $cartSkus = $this->getCurrentCartSkus();

        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

            $response = $httpClient->request(
                'POST',
                $baseUrl . '/recommend/session',
                [
                    'json' => [
                        'current_sku' => $currentSku,
                        'viewed_skus' => $viewedSkus,
                        'cart_skus' => $cartSkus,
                        'limit' => 20,
                    ],
                    'timeout' => 5,
                ]
            );

            if ($response->getStatusCode() >= 400) {
                return;
            }

            $data = $response->toArray(false);

            foreach (($data['results'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $recommendedSku = $this->getRecommendedSkuFromRow($row);
                $similarity = (float) ($row['similarity'] ?? 0);

                if ($recommendedSku === '' || $similarity <= 0) {
                    continue;
                }

                $scores[$recommendedSku]
                    = ($scores[$recommendedSku] ?? 0)
                    + ($similarity * $weight);
            }

        } catch (\Throwable $e) {
            $this->logger->warning(
                '[Recommendation][Session] Failed to fetch session recommendations',
                [
                    'currentSku' => $currentSku,
                    'message' => $e->getMessage(),
                ]
            );

            return;
        }
    }

    private function addWishlistScores(
        array &$scores,
        Request $request,
        HttpClientInterface $httpClient,
        float $weight
    ): void {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return;
        }

        $wishlistIds = $user->getWishlist();

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
        Request $request,
        HttpClientInterface $httpClient,
        float $weight
    ): void {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return;
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('oi.sku AS sku')
            ->from(OrderItem::class, 'oi')
            ->join('oi.order', 'o')
            ->where('o.customer = :customer')
            ->setParameter('customer', $user)
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

            $seedWeights[$sku] = max(
                $seedWeights[$sku] ?? 0.0,
                $weight
            );
        }

        $this->addRecommendedBatchSkuScores(
            $scores,
            $seedWeights,
            $httpClient,
            20
        );
    }

    private function addSearchHistoryScores(
        array &$scores,
        Request $request,
        HttpClientInterface $httpClient,
        float $weight = 0.25,
        int $maxQueries = 3
    ): void {
        try {
            $user = $this->getUser();
            $sessionId = $request->getSession()->getId();

            $qb = $this->entityManager
                ->getRepository(CustomerSearchLog::class)
                ->createQueryBuilder('log')
                ->orderBy('log.createdAt', 'DESC')
                ->setMaxResults($maxQueries);

            if ($user instanceof Customer) {
                $qb
                    ->where('log.customer = :customer')
                    ->setParameter('customer', $user);
            } else {
                $qb
                    ->where('log.sessionId = :sessionId')
                    ->setParameter('sessionId', $sessionId);
            }

            $logs = $qb
                ->getQuery()
                ->getResult();

            foreach ($logs as $log) {
                if (!$log instanceof CustomerSearchLog) {
                    continue;
                }

                $query = trim((string) $log->getQuery());

                if ($query === '') {
                    continue;
                }

                $response = $httpClient->request('POST', $this->searchServiceUrl . '/search', [
                    'headers' => [
                        'X-BENCHMARK' => '1',
                    ],
                    'json' => [
                        'query' => $query,
                        'limit' => 5,
                    ],
                    'timeout' => 2.0,
                    'max_duration' => 3.0,
                ]);

                $data = $response->toArray(false);
                $results = $data['results'] ?? [];

                foreach ($results as $index => $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $sku = trim((string) ($row['product_sku'] ?? $row['sku'] ?? ''));

                    if ($sku === '') {
                        continue;
                    }

                    $similarity = (float) ($row['similarity'] ?? 1.0);
                    $positionWeight = 1.0 / ($index + 1);

                    $scores[$sku] = ($scores[$sku] ?? 0.0)
                        + ($similarity * $positionWeight * $weight);
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->warning(
                '[Recommendation] Failed to add search history scores.',
                [
                    'message' => $exception->getMessage(),
                ]
            );
        }
    }

    private function addRecommendedSkuScores(
        array &$scores,
        string $sku,
        float $weight,
        HttpClientInterface $httpClient
    ): void {
        $sku = trim($sku);

        if ($sku === '') {
            return;
        }

        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

            $response = $httpClient->request(
                'GET',
                $baseUrl . '/recommend/' . rawurlencode($sku),
                [
                    'query' => [
                        'limit' => 10,
                    ],
                    'timeout' => 5,
                ]
            );

            if ($response->getStatusCode() >= 400) {
                return;
            }

            $data = $response->toArray(false);

            foreach (($data['results'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $recommendedSku = $this->getRecommendedSkuFromRow($row);
                $similarity = (float) ($row['similarity'] ?? 0);

                if ($recommendedSku !== '' && $similarity > 0) {
                    $scores[$recommendedSku]
                        = ($scores[$recommendedSku] ?? 0)
                        + ($similarity * $weight);
                }
            }

        } catch (\Throwable $e) {
            $this->logger->warning(
                '[Recommendation] Failed to add recommendation scores',
                [
                    'sku' => $sku,
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    private function saveProductViewLog(
        Request $request,
        Product $product
    ): void {
        try {

            $sku = trim((string) $product->getSku());

            if ($sku === '') {
                return;
            }

            $sessionId = $request->getSession()->getId();

            $recent = $this->entityManager
                ->getRepository(CustomerProductViewLog::class)
                ->createQueryBuilder('v')
                ->where('v.sku = :sku')
                ->andWhere('v.sessionId = :sessionId')
                ->setParameter('sku', $sku)
                ->setParameter('sessionId', $sessionId)
                ->orderBy('v.viewedAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($recent instanceof CustomerProductViewLog) {

                $seconds =
                    time() - $recent->getViewedAt()->getTimestamp();

                if ($seconds < 300) {
                    return;
                }
            }

            $user = $this->getUser();
            $customer = $user instanceof Customer ? $user : null;

            $log = new CustomerProductViewLog();

            $log->setCustomer($customer);
            $log->setProduct($product);
            $log->setSku($sku);
            $log->setSessionId($sessionId);

            $this->entityManager->persist($log);
            $this->entityManager->flush();

        } catch (\Throwable $e) {

            $this->logger->warning(
                '[ProductViewLog] Failed to save product view log',
                [
                    'productId' => $product->getId(),
                    'sku' => $product->getSku(),
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    private function addViewHistoryScores(
        array &$scores,
        Request $request,
        HttpClientInterface $httpClient,
        float $weight
    ): void {

        $user = $this->getUser();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('v.sku')
            ->from(CustomerProductViewLog::class, 'v')
            ->orderBy('v.viewedAt', 'DESC')
            ->setMaxResults(20);

        if ($user instanceof Customer) {

            $qb->where('v.customer = :customer')
                ->setParameter('customer', $user);

        } else {

            $qb->where('v.sessionId = :sessionId')
                ->setParameter(
                    'sessionId',
                    $request->getSession()->getId()
                );
        }

        $rows = $qb->getQuery()->getArrayResult();

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

    private function getRecentViewedSkus(
        Request $request,
        int $limit = 20
    ): array {
        $user = $this->getUser();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('v.sku')
            ->from(CustomerProductViewLog::class, 'v')
            ->orderBy('v.viewedAt', 'DESC')
            ->setMaxResults($limit);

        if ($user instanceof Customer) {
            $qb->where('v.customer = :customer')
                ->setParameter('customer', $user);
        } else {
            $qb->where('v.sessionId = :sessionId')
                ->setParameter('sessionId', $request->getSession()->getId());
        }

        $rows = $qb->getQuery()->getArrayResult();

        $skus = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku !== '' && !in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    private function getCurrentCartSkus(): array
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.sku AS sku')
            ->from(Cart::class, 'c')
            ->join('c.product', 'p')
            ->where('c.customer = :customer')
            ->setParameter('customer', $user)
            ->getQuery()
            ->getArrayResult();

        $skus = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku !== '' && !in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }

        return $skus;
    }
}