<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Color;
use App\Entity\Product;
use App\Entity\Size;
use App\Form\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

#[IsGranted('ROLE_WAREHOUSE_MANAGER')]
final class ProductSearchController extends BaseController
{
    public function __construct(
        Environment $twig,
        LoggerInterface $logger,
        ManagerRegistry $doctrine,
    ) {
        parent::__construct($twig, $logger, $doctrine);
    }

    /**
     * @param Product[] $products
     * @return array<int, array<string, mixed>>
     */
    private function buildProductsForView(array $products, string $locale): array
    {
        return array_map(
            static function (Product $product) use ($locale): array {
                return [
                    'id' => $product->getId(),
                    'name' => $product->getTranslatedName($locale),
                    'category' => $product->getCategory()?->getName() ?? '',
                    'colorName' => $product->getColor()?->getTranslatedName($locale) ?? '',
                    'sizeName' => $product->getSize()?->getName() ?? '',
                    'numberInStock' => $product->getNumberInStock(),
                    'createdAt' => $product->getCreatedAt(),
                    'price' => $product->getPrice(),
                    'hidden' => $product->getHidden(),
                ];
            },
            $products
        );
    }

    /**
     * Executes TF-IDF search via python-api and stores ranked results in session.
     */
    #[Route('/bms/search', name: 'bms_search', methods: ['POST'])]
    public function search(
        EntityManagerInterface $em,
        SessionInterface $session,
        LoggerInterface $logger,
        Request $request,
        HttpClientInterface $httpClient,
    ): Response {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $query = trim((string) ($payload['query'] ?? ''));
        if ($query === '') {
            return new JsonResponse(['error' => 'Empty search query'], 400);
        }

        if (mb_strlen($query) > 200) {
            return new JsonResponse(['error' => 'Query too long'], 400);
        }

        $baseUrl = (string) $this->getParameter('python_api_base_url');
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl === '') {
            $logger->error('PYTHON_API_BASE_URL is empty');
            return new JsonResponse(['error' => 'Search backend not available'], 500);
        }

        try {
            $response = $httpClient->request('POST', $baseUrl . '/search', [
                'json' => [
                    'query' => $query,
                    'limit' => 200,
                ],
                'timeout' => 5,
            ]);
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $logger->error('python-api request failed: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        if (!is_array($data) || !isset($data['results']) || !is_array($data['results'])) {
            $logger->error('python-api invalid response: ' . json_encode($data));
            return new JsonResponse(['error' => 'Search system error'], 500);
        }

        $skuToSimilarity = [];
        foreach ($data['results'] as $result) {
            if (!is_array($result)) {
                continue;
            }
            if (!isset($result['product_sku'], $result['similarity'])) {
                continue;
            }
            $sku = (string) $result['product_sku'];
            $sim = (float) $result['similarity'];
            $skuToSimilarity[$sku] = $sim;
        }

        $productSkus = array_keys($skuToSimilarity);
        if ($productSkus === []) {
            $session->set('search_results', []);
            return new JsonResponse(['results' => []]);
        }

        $qb = $em->createQueryBuilder();
        $qb->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where($qb->expr()->in('p.sku', ':skus'))
            ->setParameter('skus', $productSkus)
            ->orderBy('p.id', 'DESC');

        $rows = $qb->getQuery()->getArrayResult();

        $skuToLatestId = [];
        foreach ($rows as $row) {
            $sku = (string) $row['sku'];
            if (!isset($skuToLatestId[$sku])) {
                $skuToLatestId[$sku] = (int) $row['id'];
            }
        }

        $sortedResults = [];
        foreach ($productSkus as $sku) {
            if (isset($skuToLatestId[$sku])) {
                $sortedResults[] = [
                    'id' => $skuToLatestId[$sku],
                    'similarity' => $skuToSimilarity[$sku],
                ];
            }
        }

        $session->set('search_results', $sortedResults);

        return new JsonResponse(['results' => $sortedResults]);
    }

    /**
     * Displays the product search results stored in session.
     */
    #[Route('/bms/results', name: 'results', methods: ['GET'])]
    public function results(SessionInterface $session, EntityManagerInterface $em, Request $request): Response
    {
        $searchResults = $session->get('search_results', []);
        if (empty($searchResults)) {
            return $this->renderLocalized('product/no_results.html.twig', []);
        }

        $ids = array_column($searchResults, 'id');

        /** @var Product[] $products */
        $products = $em->getRepository(Product::class)->findBy(['id' => $ids]);

        $productsById = [];
        foreach ($products as $product) {
            $productsById[$product->getId()] = $product;
        }

        $sortedProducts = [];
        foreach ($ids as $id) {
            if (isset($productsById[$id])) {
                $sortedProducts[] = $productsById[$id];
            }
        }

        $form = $this->createForm(ProductType::class, new Product());
        $colors = $em->getRepository(Color::class)->findAll();
        $sizes = $em->getRepository(Size::class)->findAll();
        $categories = $em->getRepository(Category::class)->findAll();

        $locale = $request->getLocale();
        $productsForView = $this->buildProductsForView($sortedProducts, $locale);

        return $this->renderLocalized('product/product_list.html.twig', [
            'products' => $productsForView,
            'MAX_ARTICLES_COUNT_PER_PAGE' => $this->getParameter('MAX_ARTICLES_COUNT_PER_PAGE'),
            'NAME_MAX_LENGTH' => $this->getParameter('NAME_MAX_LENGTH'),
            'CONTENT_MAX_LENGTH' => $this->getParameter('CONTENT_MAX_LENGTH'),
            'form' => $form,
            'colors' => $colors,
            'sizes' => $sizes,
            'categories' => $categories,
        ]);
    }
}