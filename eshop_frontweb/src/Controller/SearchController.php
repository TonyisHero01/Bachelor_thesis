<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ShopInfo;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;
use App\Entity\Customer;
use App\Entity\CustomerSearchLog;
use App\Entity\OrderItem;
use App\Entity\Order;

class SearchController extends BaseController
{
    private ?ShopInfo $shopInfo = null;
    private string $searchServiceBaseUrl;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        string $searchServiceBaseUrl,
        Environment $twig,
        LoggerInterface $logger,
    ) {
        parent::__construct($twig, $logger);

        $this->searchServiceBaseUrl = rtrim($searchServiceBaseUrl, '/');

        if ($this->searchServiceBaseUrl === '') {
            $this->logger->error('[SearchController] SEARCH_SERVICE_BASE_URL missing');
        }

        $this->shopInfo = $this->entityManager
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Executes a product search using search-service and stores results in the session.
     */
    #[Route('/search', name: 'search', methods: ['POST'])]
    public function search(Request $request, SessionInterface $session): JsonResponse
    {
        $input = json_decode((string) $request->getContent(), true);
        if (!is_array($input)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $query = trim((string) ($input['query'] ?? ''));
        if ($query === '') {
            return new JsonResponse(['error' => 'Empty search query'], 400);
        }

        if (mb_strlen($query) > 200) {
            return new JsonResponse(['error' => 'Query too long'], 400);
        }

        $raw = $this->callPythonApiSearch($query, 200);
        if ($raw === null) {
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        $skuToSimilarity = [];
        foreach (($raw['results'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sku = isset($row['product_sku']) ? trim((string) $row['product_sku']) : '';
            $sim = $row['similarity'] ?? null;

            if ($sku === '' || !is_numeric($sim)) {
                continue;
            }
            $skuToSimilarity[$sku] = (float) $sim;
        }

        $productSkus = array_keys($skuToSimilarity);
        if ($productSkus === []) {
            $session->set('search_results', []);
            return new JsonResponse(['results' => []]);
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where('p.sku IN (:skus)')
            ->setParameter('skus', $productSkus)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $skuToLatestId = [];
        foreach ($rows as $row) {
            $sku = (string) ($row['sku'] ?? '');
            $id  = (int) ($row['id'] ?? 0);
            if ($sku !== '' && $id > 0 && !isset($skuToLatestId[$sku])) {
                $skuToLatestId[$sku] = $id;
            }
        }

        $sortedResults = [];
        foreach ($productSkus as $sku) {
            if (!isset($skuToLatestId[$sku])) {
                continue;
            }
            $sortedResults[] = [
                'id' => $skuToLatestId[$sku],
                'similarity' => $skuToSimilarity[$sku],
            ];
        }

        $session->set('search_results', $sortedResults);

        $this->saveCustomerSearchLog(
            $request,
            $query,
            count($sortedResults)
        );

        return new JsonResponse(['results' => $sortedResults]);
    }

    /**
     * Renders the search results page using session cached results or an optional query parameter.
     */
    #[Route('/search/results', name: 'search_results', methods: ['GET'])]
    public function searchResults(Request $request, SessionInterface $session): Response
    {
        $searchResults = $session->get('search_results', []);
        if (!is_array($searchResults)) {
            $searchResults = [];
        }

        $query = $request->query->get('query');
        if (is_string($query) && trim($query) !== '') {
            $query = trim($query);

            $searchResults = $this->runSearchAndResolveLatestProductIds($query);
            $session->set('search_results', $searchResults);

            $this->saveCustomerSearchLog(
                $request,
                $query,
                count($searchResults)
            );
        }

        $categoriesRepo = $this->entityManager->getRepository(Category::class);
        $categories = method_exists($categoriesRepo, 'findAllCategories')
            ? $categoriesRepo->findAllCategories()
            : $categoriesRepo->findAll();

        if ($searchResults === []) {
            return $this->renderLocalized(
                'search/results.html.twig',
                [
                    'products' => [],
                    'shopInfo' => $this->shopInfo,
                    'locale' => (string) $request->getLocale(),
                    'languages' => $this->getAvailableLanguages(),
                    'show_sidebar' => false,
                    'categories' => $categories,
                ],
                $request,
            );
        }

        $productIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $r): int => is_array($r) ? (int) ($r['id'] ?? 0) : 0,
            $searchResults,
        ), static fn (int $id): bool => $id > 0)));

        if ($productIds === []) {
            return $this->renderLocalized(
                'search/results.html.twig',
                [
                    'products' => [],
                    'shopInfo' => $this->shopInfo,
                    'locale' => (string) $request->getLocale(),
                    'languages' => $this->getAvailableLanguages(),
                    'show_sidebar' => false,
                    'categories' => $categories,
                ],
                $request,
            );
        }

        $productsRaw = $this->entityManager->getRepository(Product::class)->findBy(['id' => $productIds]);

        $productMap = [];
        foreach ($productsRaw as $product) {
            if (!$product instanceof Product) {
                continue;
            }
            if ($product->getImageUrls() === null || $product->getImageUrls() === []) {
                continue;
            }
            $productMap[$product->getId()] = $product;
        }

        $products = [];
        foreach ($searchResults as $r) {
            if (!is_array($r)) {
                continue;
            }

            $id = (int) ($r['id'] ?? 0);
            if ($id <= 0 || !isset($productMap[$id])) {
                continue;
            }

            $product = $productMap[$id];
            $product->similarity = (float) ($r['similarity'] ?? 0.0);
            $products[] = $product;
        }

        return $this->renderLocalized(
            'search/results.html.twig',
            [
                'products' => $products,
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
     * Runs search-service search and maps returned SKUs to the latest product IDs.
     *
     * @return array<int, array{id:int, similarity:float}>
     */
    private function runSearchAndResolveLatestProductIds(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            $this->logger->warning('[SEARCH] empty query');
            return [];
        }

        $raw = $this->callPythonApiSearch($query, 200);
        if ($raw === null) {
            return [];
        }

        $skuToSimilarity = [];
        foreach (($raw['results'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sku = isset($row['product_sku']) ? trim((string) $row['product_sku']) : '';
            $sim = $row['similarity'] ?? null;
            if ($sku === '' || !is_numeric($sim)) {
                continue;
            }
            $skuToSimilarity[$sku] = (float) $sim;
        }

        $productSkus = array_keys($skuToSimilarity);
        if ($productSkus === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where('p.sku IN (:skus)')
            ->setParameter('skus', $productSkus)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $skuToLatestId = [];
        foreach ($rows as $row) {
            $sku = (string) ($row['sku'] ?? '');
            $id  = (int) ($row['id'] ?? 0);
            if ($sku !== '' && $id > 0 && !isset($skuToLatestId[$sku])) {
                $skuToLatestId[$sku] = $id;
            }
        }

        $results = [];
        foreach ($productSkus as $sku) {
            if (!isset($skuToLatestId[$sku])) {
                continue;
            }
            $results[] = [
                'id' => $skuToLatestId[$sku],
                'similarity' => $skuToSimilarity[$sku],
            ];
        }

        return $results;
    }

    /**
     * Call search-service POST /search
     *
     * @return array<string, mixed>|null
     */
    private function callPythonApiSearch(string $query, int $limit): ?array
    {
        if ($this->searchServiceBaseUrl === '') {
            $this->logger->error('[SearchController] SEARCH_SERVICE_BASE_URL empty');
            return null;
        }

        $limit = max(1, min($limit, 200));
        $url = $this->searchServiceBaseUrl . '/search';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'query' => $query,
                    'limit' => $limit,
                ],
                'timeout' => 5,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error('[SearchController] Search service returned non-2xx response', [
                    'url' => $url,
                    'statusCode' => $statusCode,
                    'body' => substr($response->getContent(false), 0, 800),
                ]);

                return null;
            }

            $data = $response->toArray(false);

            if (!isset($data['results']) || !is_array($data['results'])) {
                $this->logger->error('[SearchController] Invalid search service response', [
                    'url' => $url,
                    'response' => $data,
                ]);

                return null;
            }

            return $data;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('[SearchController] Search service transport error', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('[SearchController] Search service exception', [
                'url' => $url,
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Gets recommended product IDs from search-service by product SKU.
     *
     * @return array<int, array{id:int, similarity:float}>
     */
    public function getRecommendedProductIdsBySku(string $sku, int $limit = 5): array
    {
        $sku = trim($sku);

        if ($sku === '') {
            return [];
        }

        $raw = $this->callPythonApiRecommend($sku, $limit);

        if ($raw === null) {
            return [];
        }

        $skuToSimilarity = [];

        foreach (($raw['results'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $recommendedSku = isset($row['product_sku']) ? trim((string) $row['product_sku']) : '';
            $similarity = $row['similarity'] ?? null;

            if ($recommendedSku === '' || !is_numeric($similarity)) {
                continue;
            }

            $skuToSimilarity[$recommendedSku] = (float) $similarity;
        }

        $recommendedSkus = array_keys($skuToSimilarity);

        if ($recommendedSkus === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where('p.sku IN (:skus)')
            ->setParameter('skus', $recommendedSkus)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $skuToLatestId = [];

        foreach ($rows as $row) {
            $rowSku = (string) ($row['sku'] ?? '');
            $id = (int) ($row['id'] ?? 0);

            if ($rowSku !== '' && $id > 0 && !isset($skuToLatestId[$rowSku])) {
                $skuToLatestId[$rowSku] = $id;
            }
        }

        $results = [];

        foreach ($recommendedSkus as $recommendedSku) {
            if (!isset($skuToLatestId[$recommendedSku])) {
                continue;
            }

            $results[] = [
                'id' => $skuToLatestId[$recommendedSku],
                'similarity' => $skuToSimilarity[$recommendedSku],
            ];
        }

        return $results;
    }

    /**
     * Call search-service GET /recommend/{sku}
     *
     * @return array<string, mixed>|null
     */
    private function callPythonApiRecommend(string $sku, int $limit): ?array
    {
        if ($this->searchServiceBaseUrl === '') {
            $this->logger->error('[SearchController] SEARCH_SERVICE_BASE_URL empty');
            return null;
        }

        $limit = max(1, min($limit, 50));
        $url = $this->searchServiceBaseUrl . '/recommend/' . rawurlencode($sku);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'limit' => $limit,
                ],
                'timeout' => 5,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error('[SearchController] Recommend service returned non-2xx response', [
                    'url' => $url,
                    'statusCode' => $statusCode,
                    'body' => substr($response->getContent(false), 0, 800),
                ]);

                return null;
            }

            $data = $response->toArray(false);

            if (!isset($data['results']) || !is_array($data['results'])) {
                $this->logger->error('[SearchController] Invalid recommend service response', [
                    'url' => $url,
                    'response' => $data,
                ]);

                return null;
            }

            return $data;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('[SearchController] Recommend service transport error', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('[SearchController] Recommend service exception', [
                'url' => $url,
                'message' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return null;
        }
    }

    private function saveCustomerSearchLog(Request $request, string $query, int $resultCount): void
    {
        try {
            $user = $this->getUser();
            $customer = $user instanceof Customer ? $user : null;

            $log = new CustomerSearchLog();
            $log->setCustomer($customer);
            $log->setQuery($query);
            $log->setResultCount($resultCount);
            $log->setSessionId($request->getSession()->getId());

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->warning('[SearchController] Failed to save customer search log', [
                'query' => $query,
                'message' => $e->getMessage(),
            ]);
        }
    }

    #[Route('/recommend/for-you', name: 'recommend_for_you', methods: ['GET'])]
    public function recommendForYou(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return new JsonResponse(['results' => []]);
        }

        $scores = [];

        $this->addWishlistRecommendationScores($scores, $user);
        $this->addOrderHistoryRecommendationScores($scores, $user);
        $this->addSearchHistoryRecommendationScores($scores, $user);

        arsort($scores);

        $skus = array_slice(array_keys($scores), 0, 20);

        if ($skus === []) {
            return new JsonResponse(['results' => []]);
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

        $results = [];

        foreach ($skus as $sku) {
            if (!isset($skuToLatestId[$sku])) {
                continue;
            }

            $results[] = [
                'id' => $skuToLatestId[$sku],
                'sku' => $sku,
                'score' => $scores[$sku],
            ];

            if (count($results) >= 10) {
                break;
            }
        }

        return new JsonResponse(['results' => $results]);
    }

    private function addWishlistRecommendationScores(array &$scores, Customer $customer): void
    {
        $wishlistIds = $customer->getWishlist();

        if ($wishlistIds === []) {
            return;
        }

        $products = $this->entityManager
            ->getRepository(Product::class)
            ->findBy(['id' => $wishlistIds]);

        foreach ($products as $product) {
            if (!$product instanceof Product || !$product->getSku()) {
                continue;
            }

            $recommended = $this->getRecommendedProductIdsBySku($product->getSku(), 10);

            foreach ($recommended as $item) {
                $recommendedProduct = $this->entityManager
                    ->getRepository(Product::class)
                    ->find($item['id']);

                if (!$recommendedProduct instanceof Product || !$recommendedProduct->getSku()) {
                    continue;
                }

                $sku = $recommendedProduct->getSku();
                $scores[$sku] = ($scores[$sku] ?? 0) + ((float) $item['similarity'] * 0.35);
            }
        }
    }

    private function addOrderHistoryRecommendationScores(array &$scores, Customer $customer): void
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

            if ($sku === '') {
                continue;
            }

            $recommended = $this->getRecommendedProductIdsBySku($sku, 10);

            foreach ($recommended as $item) {
                $product = $this->entityManager
                    ->getRepository(Product::class)
                    ->find($item['id']);

                if (!$product instanceof Product || !$product->getSku()) {
                    continue;
                }

                $recommendedSku = $product->getSku();
                $scores[$recommendedSku] = ($scores[$recommendedSku] ?? 0) + ((float) $item['similarity'] * 0.30);
            }
        }
    }

    private function addSearchHistoryRecommendationScores(array &$scores, Customer $customer): void
    {
        $logs = $this->entityManager->createQueryBuilder()
            ->select('l.query AS query')
            ->from(CustomerSearchLog::class, 'l')
            ->where('l.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();

        foreach ($logs as $log) {
            $query = trim((string) ($log['query'] ?? ''));

            if ($query === '') {
                continue;
            }

            $raw = $this->callPythonApiSearch($query, 10);

            if ($raw === null) {
                continue;
            }

            foreach (($raw['results'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $sku = trim((string) ($row['product_sku'] ?? ''));
                $similarity = (float) ($row['similarity'] ?? 0);

                if ($sku === '' || $similarity <= 0) {
                    continue;
                }

                $scores[$sku] = ($scores[$sku] ?? 0) + ($similarity * 0.25);
            }
        }
    }
}