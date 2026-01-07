<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Category;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findLatestByCategory(Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->andWhere('p.category = :category')
            ->setParameter('category', $category)

            // 只要可见的
            ->andWhere('p.hidden = false')

            // ✅ 每个 SKU 只取 id 最大那条
            ->andWhere('p.id = (
                SELECT MAX(p2.id)
                FROM App\Entity\Product p2
                WHERE p2.sku = p.sku
            )')

            // 如果你希望“最新记录必须也在同一分类里”，建议加上这一行（更严谨）
            ->andWhere('p.category = :category')

            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findTopSellingProducts(int $limit = 4): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT sku
            FROM order_items
            GROUP BY sku
            ORDER BY SUM(quantity) DESC
            LIMIT ' . (int) $limit;

        $result = $connection->executeQuery($sql);
        $topSkus = array_column($result->fetchAllAssociative(), 'sku');

        if (empty($topSkus)) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->where('p.hidden = false')
            ->andWhere('p.version = (
                SELECT MAX(p2.version)
                FROM App\Entity\Product p2
                WHERE p2.sku = p.sku
            )')
            ->andWhere('p.sku IN (:skus)')
            ->setParameter('skus', $topSkus);

        $products = $qb->getQuery()->getResult();

        return array_filter($products, fn($p) => $p->hasImages());
    }
    public function findLatestVersionProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->where('p.version = (
                SELECT MAX(p2.version)
                FROM App\Entity\Product p2
                WHERE p2.sku = p.sku
                AND (p2.size = p.size OR p2.size IS NULL)
            )')
            ->andWhere('p.hidden = false')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findLastFourProducts(): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')
            ->addSelect('t')
            ->where('p.hidden = false')
            ->andWhere('p.image_urls IS NOT NULL')
            ->andWhere('p.version = (
                SELECT MAX(p2.version)
                FROM App\Entity\Product p2
                WHERE p2.sku = p.sku
            )')
            ->orderBy('p.createdAt', 'DESC');

        $products = $qb->getQuery()->getResult();

        $seenSkus = [];
        $filtered = [];
        foreach ($products as $product) {
            if (!in_array($product->getSku(), $seenSkus) && $product->hasImages()) {
                $seenSkus[] = $product->getSku();
                $filtered[] = $product;
            }
            if (count($filtered) >= 4) break;
        }

        return $filtered;
    }
    public function findByCategoryName(string $categoryName): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.category = :categoryName')
            ->andWhere('p.hidden = false')
            ->setParameter('categoryName', $categoryName)
            ->getQuery()
            ->getResult();
    }

    public function findAllProducts(): array
    {
        return $this->findBy([], ['id' => 'DESC']);
    }

    public function findProductById($id): ?Product
    {
        return $this->find($id);
    }
    public function findOneByMaxId(): ?Product
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function deleteProduct(Product $product): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($product);
        $entityManager->flush();
    }
    public function findByIds(array $ids): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}
