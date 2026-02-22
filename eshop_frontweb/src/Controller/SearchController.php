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

class SearchController extends BaseController
{
    private ?ShopInfo $shopInfo = null;
    private string $pythonApiBaseUrl;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        Environment $twig,
        LoggerInterface $logger,
    ) {
        parent::__construct($twig, $logger);

        $this->pythonApiBaseUrl = rtrim((string) getenv('PYTHON_API_BASE_URL'), '/');
        if ($this->pythonApiBaseUrl === '') {
            $this->logger->error('[SEARCH] PYTHON_API_BASE_URL missing');
        }

        $this->shopInfo = $this->entityManager
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Executes a product search using python-api and stores results in the session.
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
            $searchResults = $this->runSearchAndResolveLatestProductIds($query);
            $session->set('search_results', $searchResults);
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
     * Runs python-api search and maps returned SKUs to the latest product IDs.
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
     * Call python-api POST /search
     *
     * @return array<string, mixed>|null
     */
    private function callPythonApiSearch(string $query, int $limit): ?array
    {
        if ($this->pythonApiBaseUrl === '') {
            $this->logger->error('[SEARCH] pythonApiBaseUrl empty');
            return null;
        }

        $limit = max(1, min($limit, 200));
        $url = $this->pythonApiBaseUrl . '/search';

        try {
            $resp = $this->httpClient->request('POST', $url, [
                'json' => [
                    'query' => $query,
                    'limit' => $limit,
                ],
                'timeout' => 5,
            ]);

            $status = $resp->getStatusCode();
            $body = $resp->getContent(false);

            if ($status < 200 || $status >= 300) {
                $this->logger->error('[SEARCH] python-api non-2xx', [
                    'url' => $url,
                    'status' => $status,
                    'body_head' => substr((string) $body, 0, 800),
                ]);
                return null;
            }

            $data = json_decode((string) $body, true);
            if (!is_array($data)) {
                $this->logger->error('[SEARCH] python-api invalid JSON', [
                    'url' => $url,
                    'status' => $status,
                    'body_head' => substr((string) $body, 0, 800),
                ]);
                return null;
            }

            return $data;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('[SEARCH] python-api transport error', [
                'url' => $url,
                'msg' => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->error('[SEARCH] python-api exception', [
                'url' => $url,
                'msg' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return null;
        }
    }
}