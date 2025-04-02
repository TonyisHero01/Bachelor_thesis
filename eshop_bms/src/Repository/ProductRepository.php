<?php

namespace App\Repository;

use App\Entity\Product;
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
    public function findLatestVersionProducts(): array
    {
        // 子查询：获取每个 SKU 的最大版本
        $subQuery = $this->createQueryBuilder('p2')
            ->select('MAX(p2.version)')
            ->where('p2.sku = p.sku')
            ->getDQL();

        // 主查询：获取 SKU 最新版本的完整产品数据
        return $this->createQueryBuilder('p')
            ->where("p.version = ($subQuery)")  // 只取最新版本
            ->orderBy('p.add_time', 'DESC')  // 按时间降序
            ->getQuery()
            ->getResult();
    }
    public function findLastFourProducts()
    {
        $results = $this->createQueryBuilder('p')
            ->where('p.image_urls IS NOT NULL') // 确保 image_urls 字段不为 NULL
            ->orderBy('p.id', 'DESC') // 根据 ID 倒序排列，确保是最新的产品
            ->getQuery()
            ->getResult();

        // 使用 PHP 过滤掉空数组的结果，并限制为 4 个
        return array_slice(array_filter($results, function ($product) {
            return !empty($product->getImageUrls());
        }), 0, 4);
    }
    public function findByCategoryName(string $categoryName): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.category = :categoryName')
            ->setParameter('categoryName', $categoryName)
            ->getQuery()
            ->getResult();
    }
    public function findLatestProductsGroupedBySku(?string $sku = null, ?string $name = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.id IN (
                SELECT MAX(p2.id)
                FROM App\Entity\Product p2
                GROUP BY p2.sku
            )')
            ->orderBy('p.sku', 'ASC');

        if ($sku) {
            $qb->andWhere('p.sku LIKE :sku')->setParameter('sku', "%$sku%");
        }

        if ($name) {
            $qb->andWhere('p.name LIKE :name')->setParameter('name', "%$name%");
        }

        return $qb->getQuery()->getResult();
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
