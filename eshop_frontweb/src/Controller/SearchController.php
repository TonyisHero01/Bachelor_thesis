<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Product;
use App\Entity\Category;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class SearchController extends AbstractController
{
    private $entityManager;
    private $shopInfo;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }

    #[Route('/search', name: 'search', methods: ['POST'])]
    public function search(Request $request, SessionInterface $session, LoggerInterface $logger): JsonResponse
    {
        // 解析前端传来的 JSON 请求体
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        $query = $input["query"] ?? '';

        if (empty($query)) {
            return new JsonResponse(['error' => 'Empty search query'], 400);
        }

        // 运行 Python 脚本
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

        // 解析 JSON 输出
        $searchResults = json_decode($output, true);
        if (!is_array($searchResults)) {
            $logger->error("Python script output: " . $output);
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        // 创建 SKU 到相似度的映射
        $skuToSimilarity = [];
        foreach ($searchResults as $result) {
            $skuToSimilarity[$result['product_sku']] = $result['similarity'];
        }

        // 获取 SKU 列表
        $productSkus = array_keys($skuToSimilarity);

        if (empty($productSkus)) {
            return new JsonResponse(["results" => []]);
        }

        // 查询数据库，获取 SKU 对应的最新产品 ID
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where($queryBuilder->expr()->in('p.sku', ':skus'))
            ->setParameter('skus', $productSkus)
            ->orderBy('p.id', 'DESC');  // 获取最新的 ID

        $products = $queryBuilder->getQuery()->getResult();
        
        // 创建 SKU 到最新 ID 的映射
        $skuToLatestId = [];
        foreach ($products as $product) {
            if (!isset($skuToLatestId[$product['sku']])) {
                $skuToLatestId[$product['sku']] = $product['id'];
            }
        }

        // 生成最终结果，包含产品 ID 和相似度
        $sortedResults = [];
        foreach ($productSkus as $sku) {
            if (isset($skuToLatestId[$sku])) {
                $sortedResults[] = [
                    'id' => $skuToLatestId[$sku],
                    'similarity' => $skuToSimilarity[$sku]
                ];
            }
        }

        // 存入 Session 以便 `/search/results` 页面使用
        $session->set('search_results', $sortedResults);

        return new JsonResponse(["results" => $sortedResults]);
    }

    #[Route('/search/results', name: 'search_results', methods: ['GET'])]
    public function searchResults(Request $request, SessionInterface $session): Response
    {
        $searchResults = $session->get('search_results', []);
        $query = $request->query->get('query');

        // 如果有URL参数query，执行搜索
        if ($query) {
            // 运行 Python 脚本
            $escapedQuery = escapeshellarg($query);
            $projectRoot = $this->getParameter('kernel.project_dir');
            $pythonPath = $projectRoot . '/python_scripts/venv/bin/python';  
            $scriptPath = $projectRoot . '/python_scripts/tf-idf.py';
            $command = "$pythonPath $scriptPath $escapedQuery 2>&1";

            $output = shell_exec($command);

            if ($output !== null) {
                $searchResults = json_decode($output, true);
                if (is_array($searchResults)) {
                    // 创建 SKU 到相似度的映射
                    $skuToSimilarity = [];
                    foreach ($searchResults as $result) {
                        $skuToSimilarity[$result['product_sku']] = $result['similarity'];
                    }

                    // 获取 SKU 列表
                    $productSkus = array_keys($skuToSimilarity);

                    if (!empty($productSkus)) {
                        // 查询数据库，获取 SKU 对应的最新产品 ID
                        $queryBuilder = $this->entityManager->createQueryBuilder();
                        $queryBuilder
                            ->select('p.id, p.sku')
                            ->from(Product::class, 'p')
                            ->where($queryBuilder->expr()->in('p.sku', ':skus'))
                            ->setParameter('skus', $productSkus)
                            ->orderBy('p.id', 'DESC');

                        $products = $queryBuilder->getQuery()->getResult();
                        
                        // 创建 SKU 到最新 ID 的映射
                        $skuToLatestId = [];
                        foreach ($products as $product) {
                            if (!isset($skuToLatestId[$product['sku']])) {
                                $skuToLatestId[$product['sku']] = $product['id'];
                            }
                        }

                        // 生成最终结果，包含产品 ID 和相似度
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
            return $this->render('search/results.html.twig', [
                'products' => [],
                'shopInfo' => $this->shopInfo,
                'show_sidebar' => false,
                'categories' => $categories
            ]);
        }

        // 获取所有产品 ID
        $productIds = array_column($searchResults, 'id');
        $productsRaw = $this->entityManager->getRepository(Product::class)->findBy(['id' => $productIds]);

        // 创建 ID 到产品的映射
        $productMap = [];
        foreach ($productsRaw as $product) {
            if (!empty($product->getImageUrls())) {
                $productMap[$product->getId()] = $product;
            }
        }

        // 按相似度排序的产品列表
        $products = [];
        foreach ($searchResults as $result) {
            $id = $result['id'];
            if (isset($productMap[$id])) {
                $product = $productMap[$id];
                $product->similarity = $result['similarity']; // 添加相似度信息到产品对象
                $products[] = $product;
            }
        }

        $categories = $this->entityManager->getRepository(Category::class)->findAllCategories();
        return $this->render('search/results.html.twig', [
            'products' => $products,
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false,
            'categories' => $categories
        ]);
    }
}