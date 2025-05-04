<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


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
    public function findTopSellingProducts(int $limit = 4): array
    {
        $connection = $this->getEntityManager()->getConnection();

        // 查询销量最高的 SKU
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

        // 查询这些 SKU 中最新版本的商品，连带翻译字段
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')  // 加载翻译
            ->addSelect('t')                   // 选择翻译数据
            ->where('p.hidden = false')
            ->andWhere('p.version = (
                SELECT MAX(p2.version)
                FROM App\Entity\Product p2
                WHERE p2.sku = p.sku
            )')
            ->andWhere('p.sku IN (:skus)')
            ->setParameter('skus', $topSkus);

        $products = $qb->getQuery()->getResult();

        // 过滤掉没有图片的
        return array_filter($products, fn($p) => $p->hasImages());
    }
    public function findLatestVersionProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.translations', 't')  // 预加载翻译
            ->addSelect('t')                   // 选择翻译数据
            ->where('p.version = (
                SELECT MAX(p2.version)
                FROM App\Entity\Product p2
                WHERE p2.sku = p.sku
                AND (p2.size = p.size OR p2.size IS NULL)
            )')
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

        // 使用 PHP 过滤掉重复 SKU，只保留前 4 个不同 SKU 的产品
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
            ->setParameter('categoryName', $categoryName)
            ->getQuery()
            ->getResult();
    }
    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

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
