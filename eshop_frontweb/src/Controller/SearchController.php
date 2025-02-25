<?php

namespace App\Controller;

use App\Entity\ShopInfo;
use App\Entity\Product;
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
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->shopInfo = $entityManager->getRepository(ShopInfo::class)->findOneBy([], ['id' => 'DESC']);
    }
    // src/Controller/SearchController.php
    #[Route('/search', name: 'search', methods: ['POST'])]
    public function search(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, LoggerInterface $logger): JsonResponse
    {
        // 解析前端传来的 JSON 请求体
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        $query = $input["query"] ?? '';

        if (empty($query)) {
            return new JsonResponse(['error' => 'Empty search query'], 400);
        }

        // 执行 Python 脚本
        $escapedQuery = escapeshellarg($query);
        $command = "python3 ../python_scripts/tf-idf.py $escapedQuery";
        $logger->info("Executing search command: " . $command);
        
        $output = shell_exec($command);

        if ($output === null) {
            $logger->error("Python script execution failed: " . $command);
            return new JsonResponse(['error' => 'Search command failed'], 500);
        }

        // 解析 Python 脚本返回的 JSON
        $searchResults = json_decode($output, true);
        if (!is_array($searchResults)) {
            $logger->error("Invalid JSON returned from Python: " . $output);
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        // 提取商品 ID
        $productIds = array_column($searchResults, 'product_id');
        $logger->info("Python search results: " . json_encode($productIds));

        if (empty($productIds)) {
            return new JsonResponse(["results" => []]);
        }

        // 将结果存入 Session 以便在 `/search/results` 使用
        $session->set('search_results', $productIds);

        // 返回前端 JSON 响应，前端会进行跳转
        return new JsonResponse(["results" => $productIds]);
    }

    #[Route('/search/results', name: 'search_results', methods: ['GET'])]
    public function searchResults(SessionInterface $session, EntityManagerInterface $entityManager): Response
    {
        $productIds = $session->get('search_results', []);

        if (empty($productIds)) {
            return $this->render('search/results.html.twig', [
                'products' => [],
                'shopInfo' => $this->shopInfo,
                'show_sidebar' => false
            ]);
        }

        // 获取数据库中的商品
        $productsRaw = $entityManager->getRepository(Product::class)->findBy(['id' => $productIds]);

        // 按照搜索顺序重新排列，并只保留有图片的商品
        $products = [];
        $productMap = [];
        foreach ($productsRaw as $product) {
            if (!empty($product->getImageUrls())) {  // 只存入有图片的商品
                $productMap[$product->getId()] = $product;
            }
        }

        foreach ($productIds as $id) {
            if (isset($productMap[$id])) {
                $products[] = $productMap[$id];
            }
        }

        return $this->render('search/results.html.twig', [
            'products' => $products,
            'shopInfo' => $this->shopInfo,
            'show_sidebar' => false
        ]);
    }
}