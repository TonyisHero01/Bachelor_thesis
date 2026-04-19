<?php

declare(strict_types=1);

namespace App\Api\Controller\V1;

use App\Entity\Product;
use App\Repository\ProductRepository;
use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PRODUCTS_READ')]
#[Route('/api/v1/products', name: 'api_v1_products_')]
class ProductApiController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * Returns a paginated product list with filters, sorting, and translations.
     *
     * Query parameters:
     * - q: keyword for name/sku/description (LIKE search)
     * - category_id: int
     * - color_id: int
     * - size_id: int
     * - hidden: "0"|"1"|"all" (default: "0")
     * - in_stock: "0"|"1" (when "1": number_in_stock > 0)
     * - price_min: number
     * - price_max: number
     * - created_from: "YYYY-MM-DD"
     * - created_to: "YYYY-MM-DD"
     * - locale: language code (default: "en")
     * - sort: id|price|createdAt|updatedAt|name (default: id)
     * - order: ASC|DESC (default: DESC)
     * - page: int (default: 1)
     * - per_page: int (default: 20, max: 100)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $locale = (string) $request->query->get('locale', 'en');

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        $qb = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'cat')->addSelect('cat')
            ->leftJoin('p.color', 'col')->addSelect('col')
            ->leftJoin('p.size', 'sz')->addSelect('sz')
            ->leftJoin('p.currency', 'cur')->addSelect('cur');

        $this->applyFilters($qb, $request);

        [$sortField, $sortOrder] = $this->resolveSorting($request);
        $qb->orderBy(sprintf('p.%s', $sortField), $sortOrder);

        $qb->setFirstResult($offset)->setMaxResults($perPage);

        /** @var Product[] $items */
        $items = $qb->getQuery()->getResult();

        $countQb = $this->productRepository->createQueryBuilder('p')->select('COUNT(p.id)');
        $this->applyFilters($countQb, $request);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $data = array_map(
            fn (Product $product): array => $this->serializeProduct($product, $locale),
            $items
        );

        return $this->json([
            'status' => 'success',
            'data' => [
                'items' => $data,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                ],
            ],
        ]);
    }

    /**
     * Returns a single product detail by identifier, including meta fields.
     *
     * Query parameters:
     * - locale: language code (default: "en")
     */
    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(int $id, Request $request): JsonResponse
    {
        $locale = (string) $request->query->get('locale', 'en');

        /** @var Product|null $product */
        $product = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'cat')->addSelect('cat')
            ->leftJoin('p.color', 'col')->addSelect('col')
            ->leftJoin('p.size', 'sz')->addSelect('sz')
            ->leftJoin('p.currency', 'cur')->addSelect('cur')
            ->andWhere('p.id = :id')->setParameter('id', $id)
            ->getQuery()->getOneOrNullResult();

        if ($product === null) {
            return $this->json(
                ['status' => 'error', 'message' => 'Product not found'],
                404
            );
        }

        return $this->json([
            'status' => 'success',
            'data' => $this->serializeProduct($product, $locale, true),
        ]);
    }

    /**
     * Serializes a product including discount calculation and translations.
     *
     * @return array<string, mixed>
     */
    private function serializeProduct(Product $product, string $locale, bool $withMeta = false): array
    {
        $basePrice = (float) ($product->getPrice() ?? 0.0);
        $discountPct = (float) $product->getDiscount();
        $finalPrice = $this->calcDiscounted($basePrice, $discountPct);

        $data = [
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getTranslatedName($locale),
            'description' => $product->getTranslatedDescription($locale),
            'material' => $product->getTranslatedMaterial($locale),
            'price' => number_format($basePrice, 2, '.', ''),
            'discount_percent' => $discountPct,
            'price_after_discount' => number_format($finalPrice, 2, '.', ''),
            'currency' => $product->getCurrency()?->getName(),
            'tax_rate' => $product->getTaxRate(),
            'number_in_stock' => $product->getNumberInStock(),
            'hidden' => $product->getHidden(),
            'image_urls' => $product->getImageUrls() ?? [],
            'category' => $product->getCategory()?->getId()
                ? [
                    'id' => $product->getCategory()->getId(),
                    'name' => $product->getCategory()->getTranslatedName($locale),
                ]
                : null,
            'color' => $product->getColor()?->getId()
                ? [
                    'id' => $product->getColor()->getId(),
                    'name' => $product->getColor()->getTranslatedName($locale),
                    'hex' => $product->getColor()->getHex(),
                ]
                : null,
            'size' => $product->getSize()?->getId()
                ? [
                    'id' => $product->getSize()->getId(),
                    'name' => (string) $product->getSize(),
                ]
                : null,
            'attributes' => $product->getAttributes(),
        ];

        if ($withMeta) {
            $data['meta'] = [
                'created_at' => $product->getCreatedAt()?->format(DATE_ATOM),
                'updated_at' => $product->getUpdatedAt()?->format(DATE_ATOM),
                'version' => $product->getVersion(),
                'dimensions' => [
                    'width' => $product->getWidth(),
                    'height' => $product->getHeight(),
                    'length' => $product->getLength(),
                    'weight' => $product->getWeight(),
                ],
            ];
        }

        return $data;
    }

    /**
     * Calculates discounted price using: price * (discountPercent / 100).
     */
    private function calcDiscounted(float $price, float $discountPercent): float
    {
        if ($discountPercent <= 0.0) {
            return 0.0;
        }

        return round($price * ($discountPercent / 100.0), 2);
    }

    /**
     * Applies common filters to the query builder.
     */
    private function applyFilters(QueryBuilder $qb, Request $request): void
    {
        $q = trim((string) $request->query->get('q', ''));
        $categoryId = $request->query->get('category_id');
        $colorId = $request->query->get('color_id');
        $sizeId = $request->query->get('size_id');
        $hidden = (string) $request->query->get('hidden', '0');
        $inStock = $request->query->get('in_stock');
        $priceMin = $request->query->get('price_min');
        $priceMax = $request->query->get('price_max');
        $createdFrom = $request->query->get('created_from');
        $createdTo = $request->query->get('created_to');

        if ($q !== '') {
            $qb->andWhere('p.name LIKE :q OR p.sku LIKE :q OR p.description LIKE :q')
                ->setParameter('q', sprintf('%%%s%%', $q));
        }

        if ($categoryId !== null && $categoryId !== '') {
            $qb->andWhere('cat.id = :catId')
                ->setParameter('catId', (int) $categoryId);
        }

        if ($colorId !== null && $colorId !== '') {
            $qb->andWhere('col.id = :colorId')
                ->setParameter('colorId', (int) $colorId);
        }

        if ($sizeId !== null && $sizeId !== '') {
            $qb->andWhere('sz.id = :sizeId')
                ->setParameter('sizeId', (int) $sizeId);
        }

        if ($hidden !== 'all') {
            $qb->andWhere('p.hidden = :hidden')
                ->setParameter('hidden', $hidden === '1' || strtolower($hidden) === 'true');
        }

        if ($inStock !== null && $inStock !== '') {
            $inStockBool = $inStock === '1' || strtolower((string) $inStock) === 'true';
            if ($inStockBool) {
                $qb->andWhere('p.number_in_stock > 0');
            }
        }

        if ($priceMin !== null && $priceMin !== '') {
            $qb->andWhere('p.price >= :pmin')
                ->setParameter('pmin', (float) $priceMin);
        }

        if ($priceMax !== null && $priceMax !== '') {
            $qb->andWhere('p.price <= :pmax')
                ->setParameter('pmax', (float) $priceMax);
        }

        if ($createdFrom !== null && $createdFrom !== '') {
            $qb->andWhere('p.createdAt >= :createdFrom')
                ->setParameter('createdFrom', new DateTimeImmutable(sprintf('%s 00:00:00', $createdFrom)));
        }

        if ($createdTo !== null && $createdTo !== '') {
            $qb->andWhere('p.updatedAt <= :createdTo')
                ->setParameter('createdTo', new DateTimeImmutable(sprintf('%s 23:59:59', $createdTo)));
        }
    }

    /**
     * Resolves sort field and sort order with validation.
     *
     * @return array{0:string,1:'ASC'|'DESC'}
     */
    private function resolveSorting(Request $request): array
    {
        $sort = (string) $request->query->get('sort', 'id');
        $order = strtoupper((string) $request->query->get('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $allowed = ['id', 'price', 'createdAt', 'updatedAt', 'name'];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'id';
        }

        return [$sort, $order];
    }
}