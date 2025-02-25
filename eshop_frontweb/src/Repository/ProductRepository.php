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
        return $this->createQueryBuilder('p')
            ->where('p.version = (
                SELECT MAX(p2.version)
                FROM App\Entity\Product p2
                WHERE p2.sku = p.sku
                AND (p2.size = p.size OR p2.size IS NULL)
            )')
            ->orderBy('p.add_time', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findLastFourProducts(): array
    {
        $products = $this->createQueryBuilder('p')
            ->where('p.image_urls IS NOT NULL')
            ->andWhere('p.hidden = false') // 直接在查询中排除 hidden = true 的商品
            ->orderBy('p.add_time', 'DESC') // 按 add_time 降序排序
            ->getQuery()
            ->getResult();

        // **用 PHP 过滤相同 SKU**
        $seenSkus = [];
        $filteredProducts = [];
        foreach ($products as $product) {
            if (!in_array($product->getSku(), $seenSkus)) { // 只保留 SKU 不重复的商品
                $seenSkus[] = $product->getSku();
                $filteredProducts[] = $product;

                // **一旦找到 4 个不同 SKU 的商品，直接返回**
                if (count($filteredProducts) >= 4) {
                    break;
                }
            }
        }

        return $filteredProducts; // 返回最多 4 个不同 SKU 的商品
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
