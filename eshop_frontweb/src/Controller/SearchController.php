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
use Twig\Environment;

class SearchController extends BaseController
{
    private ?ShopInfo $shopInfo = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        Environment $twig,
        LoggerInterface $logger,
    ) {
        parent::__construct($twig, $logger);

        $this->shopInfo = $this->entityManager
            ->getRepository(ShopInfo::class)
            ->findOneBy([], ['id' => 'DESC']);
    }

    /**
     * Executes a product search using the Python TF-IDF script and stores results in the session.
     */
    #[Route('/search', name: 'search', methods: ['POST'])]
    public function search(Request $request, SessionInterface $session, LoggerInterface $logger): JsonResponse
    {
        $input = json_decode((string) $request->getContent(), true);
        if (!is_array($input)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $query = trim((string) ($input['query'] ?? ''));
        if ($query === '') {
            return new JsonResponse(['error' => 'Empty search query'], 400);
        }

        $escapedQuery = escapeshellarg($query);
        $projectRoot = (string) $this->getParameter('kernel.project_dir');
        $pythonPath = $projectRoot . '/python_scripts/venv/bin/python';
        $scriptPath = $projectRoot . '/python_scripts/tf-idf.py';
        $command = $pythonPath . ' ' . $scriptPath . ' ' . $escapedQuery . ' 2>&1';

        $output = shell_exec($command);
        if ($output === null) {
            $logger->error('Python search script execution failed with no output');
            return new JsonResponse(['error' => 'Search command failed'], 500);
        }

        $searchResults = json_decode($output, true);
        if (!is_array($searchResults)) {
            $logger->error('Python search script returned invalid JSON', ['output' => $output]);
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        $skuToSimilarity = [];
        foreach ($searchResults as $row) {
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
            $id = (int) ($row['id'] ?? 0);
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
     * Runs the TF-IDF search script and maps returned SKUs to the latest product IDs.
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

        $projectRoot = (string) $this->getParameter('kernel.project_dir');
        $pythonPath  = $projectRoot . '/python_scripts/venv/bin/python';
        $scriptPath  = $projectRoot . '/python_scripts/tf-idf.py';

        // --- 1) run python with separated stdout/stderr (NO "2>&1") ---
        if (!is_file($pythonPath)) {
            $this->logger->error('[SEARCH] python not found', ['pythonPath' => $pythonPath]);
            return [];
        }
        if (!is_file($scriptPath)) {
            $this->logger->error('[SEARCH] script not found', ['scriptPath' => $scriptPath]);
            return [];
        }

        // Use Symfony Process (stable)
        try {
            $process = new \Symfony\Component\Process\Process([$pythonPath, $scriptPath, $query]);
            $process->setTimeout(20); // seconds, adjust if you want
            $process->run();

            $exit = $process->getExitCode();
            $out  = (string) $process->getOutput();
            $err  = (string) $process->getErrorOutput();

            $this->logger->info('[SEARCH] python finished', [
                'exit' => $exit,
                'stderr_len' => strlen($err),
                'stdout_len' => strlen($out),
                'stderr_head' => substr($err, 0, 400),
                'stdout_head' => substr($out, 0, 400),
            ]);

            if ($exit !== 0) {
                // python error -> return empty
                $this->logger->error('[SEARCH] python non-zero exit', [
                    'exit' => $exit,
                    'stderr' => $err,
                    'stdout' => substr($out, 0, 1000),
                ]);
                return [];
            }

            if (trim($out) === '') {
                $this->logger->warning('[SEARCH] python stdout empty', ['stderr' => $err]);
                return [];
            }

            // --- 2) decode JSON output robustly ---
            $rawResults = json_decode($out, true);

            // If stdout had extra prints and is not pure JSON, try to extract last JSON array
            if (!is_array($rawResults)) {
                $maybeJson = $this->extractLastJsonArray($out);
                if ($maybeJson !== null) {
                    $rawResults = json_decode($maybeJson, true);
                }
            }

            if (!is_array($rawResults)) {
                $this->logger->error('[SEARCH] python output not valid JSON', [
                    'stdout_head' => substr($out, 0, 800),
                    'stderr_head' => substr($err, 0, 800),
                ]);
                return [];
            }

        } catch (\Throwable $e) {
            $this->logger->error('[SEARCH] python execution exception', [
                'msg' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return [];
        }

        // --- 3) normalize (sku, similarity) ---
        $skuToSimilarity = [];
        foreach ($rawResults as $row) {
            if (!is_array($row)) {
                continue;
            }

            // your python returns product_sku and similarity
            $sku = isset($row['product_sku']) ? trim((string) $row['product_sku']) : '';
            $sim = $row['similarity'] ?? null;

            if ($sku === '' || !is_numeric($sim)) {
                continue;
            }

            // normalize sku (optional): trim + keep as-is; if needed you can strtoupper()
            $skuToSimilarity[$sku] = (float) $sim;
        }

        $productSkus = array_keys($skuToSimilarity);
        if ($productSkus === []) {
            $this->logger->info('[SEARCH] no usable sku from python results');
            return [];
        }

        // --- 4) map sku -> latest product id (by MAX(id)) ---
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

            // first seen is the highest id because we orderBy DESC
            if ($sku !== '' && $id > 0 && !isset($skuToLatestId[$sku])) {
                $skuToLatestId[$sku] = $id;
            }
        }

        // Keep python order (relevance order)
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

        $this->logger->info('[SEARCH] resolved results', [
            'query' => $query,
            'python_skus' => count($productSkus),
            'resolved' => count($results),
        ]);

        return $results;
    }

    /**
     * Try to extract the last JSON array from a mixed stdout string.
     * Example stdout:
     *   "warning...\n[ {...}, {...} ]\n"
     */
    private function extractLastJsonArray(string $out): ?string
    {
        // find last '[' and last ']' after it
        $lastOpen = strrpos($out, '[');
        $lastClose = strrpos($out, ']');

        if ($lastOpen === false || $lastClose === false || $lastClose <= $lastOpen) {
            return null;
        }

        $candidate = substr($out, $lastOpen, $lastClose - $lastOpen + 1);
        $candidate = trim($candidate);

        // quick sanity check
        if ($candidate === '' || $candidate[0] !== '[' || substr($candidate, -1) !== ']') {
            return null;
        }

        return $candidate;
    }
}