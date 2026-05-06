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
use App\Entity\SearchRelevanceConfig;

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

    /**
     * Displays product detail page for the given product id.
     */
    #[Route('/product/{id}', name: 'show_eshop_product', methods: ['GET'])]
    public function show(Request $request, int $id, HttpClientInterface $httpClient): Response
    {
        $productRepo = $this->entityManager->getRepository(Product::class);

        $product = method_exists($productRepo, 'findProductById')
            ? $productRepo->findProductById($id)
            : $productRepo->find($id);

        if (!$product instanceof Product) {
            throw $this->createNotFoundException(sprintf('No product found for id %d', $id));
        }

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        $shopInfo = $this->entityManager->getRepository(ShopInfo::class)->findOneBy([]);

        $recommendedProducts = $this->buildHybridRecommendations(
            $request,
            $product,
            $httpClient,
            5
        );

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

    private function buildHybridRecommendations(
        Request $request,
        Product $currentProduct,
        HttpClientInterface $httpClient,
        int $limit = 5
    ): array {
        $scores = [];

        $config = $this->entityManager
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        $tfidfWeight = $config?->getTfidfRecommendationWeight() ?? 1.0;
        $sameCategoryWeight = $config?->getSameCategoryRecommendationWeight() ?? 0.35;
        $sameColorWeight = $config?->getSameColorRecommendationWeight() ?? 0.10;
        $sameSizeWeight = $config?->getSameSizeRecommendationWeight() ?? 0.10;
        $wishlistWeight = $config?->getWishlistRecommendationWeight() ?? 0.30;
        $orderWeight = $config?->getOrderHistoryRecommendationWeight() ?? 0.25;
        $searchWeight = $config?->getSearchHistoryRecommendationWeight() ?? 0.20;

        $this->addTfidfScores($scores, $currentProduct, $httpClient, $tfidfWeight);
        $this->addAttributeScores($scores, $currentProduct, $sameCategoryWeight, $sameColorWeight, $sameSizeWeight);
        $this->addWishlistScores($scores, $request, $httpClient, $wishlistWeight);
        $this->addOrderHistoryScores($scores, $request, $httpClient, $orderWeight);
        $this->addSearchHistoryScores($scores, $request, $httpClient, $searchWeight);

        unset($scores[$currentProduct->getSku()]);

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

    private function addTfidfScores(
        array &$scores,
        Product $product,
        HttpClientInterface $httpClient,
        float $weight
    ): void {
        $sku = trim((string) $product->getSku());

        if ($sku === '') {
            return;
        }

        try {
            $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

            $response = $httpClient->request('GET', $baseUrl . '/recommend/' . rawurlencode($sku), [
                'query' => ['limit' => 20],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() >= 400) {
                return;
            }

            $data = $response->toArray(false);

            foreach (($data['results'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $recommendedSku = trim((string) ($row['product_sku'] ?? ''));
                $similarity = (float) ($row['similarity'] ?? 0);

                if ($recommendedSku === '' || $similarity <= 0) {
                    continue;
                }

                $scores[$recommendedSku] = ($scores[$recommendedSku] ?? 0) + ($similarity * $weight);
            }
        } catch (\Throwable) {
            return;
        }
    }

    private function addAttributeScores(
        array &$scores,
        Product $currentProduct,
        float $sameCategoryWeight,
        float $sameColorWeight,
        float $sameSizeWeight
    ): void {
        $queryBuilder = $this->entityManager->createQueryBuilder();

        $candidates = $queryBuilder
            ->select('p')
            ->from(Product::class, 'p')
            ->where('p.sku != :currentSku')
            ->andWhere('p.hidden = false')
            ->setParameter('currentSku', $currentProduct->getSku())
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        foreach ($candidates as $candidate) {
            if (!$candidate instanceof Product || !$candidate->getSku()) {
                continue;
            }

            $score = 0.0;

            if ($currentProduct->getCategory() && $candidate->getCategory()
                && $currentProduct->getCategory()->getId() === $candidate->getCategory()->getId()) {
                $score += $sameCategoryWeight;
            }

            if ($currentProduct->getColor() && $candidate->getColor()
                && $currentProduct->getColor()->getId() === $candidate->getColor()->getId()) {
                $score += $sameColorWeight;
            }

            if ($currentProduct->getSize() && $candidate->getSize()
                && $currentProduct->getSize()->getId() === $candidate->getSize()->getId()) {
                $score += $sameSizeWeight;
            }

            if ($score > 0) {
                $scores[$candidate->getSku()] = ($scores[$candidate->getSku()] ?? 0) + $score;
            }
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
            ->setMaxResults(50)
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku !== '') {
                $this->addRecommendedSkuScores($scores, $sku, $weight, $httpClient);
            }
        }
    }

    private function addSearchHistoryScores(
        array &$scores,
        Request $request,
        HttpClientInterface $httpClient,
        float $weight
    ): void {
        $user = $this->getUser();

        $qb = $this->entityManager->createQueryBuilder()
            ->select('l.query')
            ->from(CustomerSearchLog::class, 'l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(5);

        if ($user instanceof Customer) {
            $qb->where('l.customer = :customer')
                ->setParameter('customer', $user);
        } else {
            $qb->where('l.sessionId = :sessionId')
                ->setParameter('sessionId', $request->getSession()->getId());
        }

        $logs = $qb->getQuery()->getArrayResult();

        foreach ($logs as $log) {
            $query = trim((string) ($log['query'] ?? ''));

            if ($query === '') {
                continue;
            }

            try {
                $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

                $response = $httpClient->request('POST', $baseUrl . '/search', [
                    'json' => [
                        'query' => $query,
                        'limit' => 10,
                    ],
                    'timeout' => 5,
                ]);

                if ($response->getStatusCode() >= 400) {
                    continue;
                }

                $data = $response->toArray(false);

                foreach (($data['results'] ?? []) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $sku = trim((string) ($row['product_sku'] ?? ''));
                    $similarity = (float) ($row['similarity'] ?? 0);

                    if ($sku !== '' && $similarity > 0) {
                        $scores[$sku] = ($scores[$sku] ?? 0) + ($similarity * $weight);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
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

            $response = $httpClient->request('GET', $baseUrl . '/recommend/' . rawurlencode($sku), [
                'query' => ['limit' => 10],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() >= 400) {
                return;
            }

            $data = $response->toArray(false);

            foreach (($data['results'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $recommendedSku = trim((string) ($row['product_sku'] ?? ''));
                $similarity = (float) ($row['similarity'] ?? 0);

                if ($recommendedSku !== '' && $similarity > 0) {
                    $scores[$recommendedSku] = ($scores[$recommendedSku] ?? 0) + ($similarity * $weight);
                }
            }
        } catch (\Throwable) {
            return;
        }
    }
}