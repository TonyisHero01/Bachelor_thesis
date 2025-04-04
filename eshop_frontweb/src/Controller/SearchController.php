<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Product;
use App\Entity\Category;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class SearchController extends BaseController
{
    private $entityManager;
    private $shopInfo;

    public function __construct(EntityManagerInterface $entityManager, Environment $twig, LoggerInterface $logger)
    {
        parent::__construct($twig, $logger);
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/search', name: 'search', methods: ['POST'])]
    public function search(Request $request, SessionInterface $session, LoggerInterface $logger): JsonResponse
    {
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        $query = $input["query"] ?? '';

        if (empty($query)) {
            return new JsonResponse(['error' => 'Empty search query'], 400);
        }

        $escapedQuery = escapeshellarg($query);
        $projectRoot = $this->getParameter('kernel.project_dir');
        $pythonPath = $projectRoot . '/python_scripts/venv/bin/python';
        $scriptPath = $projectRoot . '/python_scripts/tf-idf.py';
        $command = "$pythonPath $scriptPath $escapedQuery 2>&1";

        $logger->info("Executing search command: " . $command);
        $output = shell_exec($command);

        if ($output === null) {
            $logger->error("Python script execution failed with no output");
            return new JsonResponse(['error' => 'Search command failed'], 500);
        }

        $searchResults = json_decode($output, true);
        if (!is_array($searchResults)) {
            $logger->error("Python script output: " . $output);
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        $skuToSimilarity = [];
        foreach ($searchResults as $result) {
            $skuToSimilarity[$result['product_sku']] = $result['similarity'];
        }

        $productSkus = array_keys($skuToSimilarity);

        if (empty($productSkus)) {
            return new JsonResponse(["results" => []]);
        }

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where($queryBuilder->expr()->in('p.sku', ':skus'))
            ->setParameter('skus', $productSkus)
            ->orderBy('p.id', 'DESC');

        $products = $queryBuilder->getQuery()->getResult();

        $skuToLatestId = [];
        foreach ($products as $product) {
            if (!isset($skuToLatestId[$product['sku']])) {
                $skuToLatestId[$product['sku']] = $product['id'];
            }
        }

        $sortedResults = [];
        foreach ($productSkus as $sku) {
            if (isset($skuToLatestId[$sku])) {
                $sortedResults[] = [
                    'id' => $skuToLatestId[$sku],
                    'similarity' => $skuToSimilarity[$sku]
                ];
            }
        }

        $session->set('search_results', $sortedResults);

        return new JsonResponse(["results" => $sortedResults]);
    }

    #[Route('/search/results', name: 'search_results', methods: ['GET'])]
    public function searchResults(Request $request, SessionInterface $session): Response
    {
        $searchResults = $session->get('search_results', []);
        $query = $request->query->get('query');

        if ($query) {
            $escapedQuery = escapeshellarg($query);
            $projectRoot = $this->getParameter('kernel.project_dir');
            $pythonPath = $projectRoot . '/python_scripts/venv/bin/python';
            $scriptPath = $projectRoot . '/python_scripts/tf-idf.py';
            $command = "$pythonPath $scriptPath $escapedQuery 2>&1";

            $output = shell_exec($command);

            if ($output !== null) {
                $searchResults = json_decode($output, true);
                if (is_array($searchResults)) {
                    $skuToSimilarity = [];
                    foreach ($searchResults as $result) {
                        $skuToSimilarity[$result['product_sku']] = $result['similarity'];
                    }

                    $productSkus = array_keys($skuToSimilarity);

                    if (!empty($productSkus)) {
                        $queryBuilder = $this->entityManager->createQueryBuilder();
                        $queryBuilder
                            ->select('p.id, p.sku')
                            ->from(Product::class, 'p')
                            ->where($queryBuilder->expr()->in('p.sku', ':skus'))
                            ->setParameter('skus', $productSkus)
                            ->orderBy('p.id', 'DESC');

                        $products = $queryBuilder->getQuery()->getResult();

                        $skuToLatestId = [];
                        foreach ($products as $product) {
                            if (!isset($skuToLatestId[$product['sku']])) {
                                $skuToLatestId[$product['sku']] = $product['id'];
                            }
                        }

                        $searchResults = [];
                        foreach ($productSkus as $sku) {
                            if (isset($skuToLatestId[$sku])) {
                                $searchResults[] = [
                                    'id' => $skuToLatestId[$sku],
                                    'similarity' => $skuToSimilarity[$sku]
                                ];
                            }
                        }
                    }
                }
            }
        }

        if (empty($searchResults)) {
            $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
            return $this->renderLocalized('search/results.html.twig', [
                'products' => [],
                'shopInfo' => $this->shopInfo,
                'locale' => $request->getLocale(),
                'languages' => $this->getAvailableLanguages(),
                'show_sidebar' => false,
                'categories' => $categories
            ], $request);
        }

        $productIds = array_column($searchResults, 'id');
        $productsRaw = $this->entityManager->getRepository(Product::class)->findBy(['id' => $productIds]);

        $productMap = [];
        foreach ($productsRaw as $product) {
            if (!empty($product->getImageUrls())) {
                $productMap[$product->getId()] = $product;
            }
        }

        $products = [];
        foreach ($searchResults as $result) {
            $id = $result['id'];
            if (isset($productMap[$id])) {
                $product = $productMap[$id];
                $product->similarity = $result['similarity'];
                $products[] = $product;
            }
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->renderLocalized('search/results.html.twig', [
            'products' => $products,
            'shopInfo' => $this->shopInfo,
            'locale' => $request->getLocale(),
            'languages' => $this->getAvailableLanguages(),
            'show_sidebar' => false,
            'categories' => $categories
        ], $request);
    }
}