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
use App\Entity\SearchRelevanceConfig;

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
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $query = trim((string) ($payload['query'] ?? ''));

        if ($query === '') {
            return new JsonResponse(['error' => 'Empty search query'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($query) > 200) {
            return new JsonResponse(['error' => 'Query too long'], Response::HTTP_BAD_REQUEST);
        }

        $baseUrl = rtrim((string) $this->getParameter('search_service_base_url'), '/');

        $searchConfig = $em
            ->getRepository(SearchRelevanceConfig::class)
            ->findOneBy(['active' => true], ['id' => 'DESC']);

        $searchMethod = $searchConfig?->getSearchMethod() ?? 'tfidf';

        $searchEndpoint = match ($searchMethod) {
            'semantic_vector' => '/semantic/search',
            default => '/search',
        };

        if ($baseUrl === '') {
            $logger->error('[ProductSearchController] SEARCH_SERVICE_BASE_URL is empty');
            return new JsonResponse(['error' => 'Search backend not available'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $response = $httpClient->request('POST', $baseUrl . $searchEndpoint, [
                'json' => [
                    'query' => $query,
                    'limit' => 200,
                ],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() >= 400) {
                $logger->error('[ProductSearchController] Search service returned error', [
                    'status_code' => $response->getStatusCode(),
                    'body' => $response->getContent(false),
                ]);

                return new JsonResponse(['error' => 'Search system error'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $logger->error('[ProductSearchController] Search service request failed: ' . $e->getMessage());

            return new JsonResponse(['error' => 'Search system error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (!isset($data['results']) || !is_array($data['results'])) {
            $logger->error('[ProductSearchController] Invalid search response', [
                'response' => $data,
            ]);

            return new JsonResponse(['error' => 'Search system error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $skuToSimilarity = [];

        foreach ($data['results'] as $result) {
            if (!is_array($result)) {
                continue;
            }

            $sku = '';

            if ($searchMethod === 'semantic_vector') {
                $sku = isset($result['sku'])
                    ? trim((string) $result['sku'])
                    : '';
            } else {
                $sku = isset($result['product_sku'])
                    ? trim((string) $result['product_sku'])
                    : '';
            }
            $similarity = isset($result['similarity']) ? (float) $result['similarity'] : 0.0;

            if ($sku === '' || $similarity <= 0) {
                continue;
            }

            $skuToSimilarity[$sku] = $similarity;
        }

        if ($skuToSimilarity === []) {
            $session->set('search_results', []);
            return new JsonResponse(['results' => []]);
        }

        $productSkus = array_keys($skuToSimilarity);

        $rows = $em->createQueryBuilder()
            ->select('p.id, p.sku')
            ->from(Product::class, 'p')
            ->where('p.sku IN (:skus)')
            ->setParameter('skus', $productSkus)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $skuToLatestId = [];

        foreach ($rows as $row) {
            $sku = (string) $row['sku'];

            if (!isset($skuToLatestId[$sku])) {
                $skuToLatestId[$sku] = (int) $row['id'];
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

        return new JsonResponse([
            'results' => $sortedResults,
        ]);
    }

    /**
     * Displays the product search results stored in session.
     */
    #[Route('/bms/results', name: 'results', methods: ['GET'])]
    public function results(SessionInterface $session, EntityManagerInterface $em, Request $request): Response
    {
        $searchResults = $session->get('search_results', []);
        if (empty($searchResults)) {
            return $this->renderLocalized('product/no_results.html.twig', [], $request);
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
        ], $request);
    }
}