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
    /**
     * Returns the latest product row for each SKU based on MAX(version),
     * and breaks ties with MAX(id) (in case same SKU + same version exists).
     *
     * @return Product[]
     */
    public function findLatestVersionProducts(?string $skuFilter = null, ?string $nameFilter = null): array
    {
        // subquery: max version for each sku
        $maxVersionDql = $this->createQueryBuilder('p2')
            ->select('MAX(p2.version)')
            ->where('p2.sku = p.sku')
            ->getDQL();

        // subquery: within latest version, pick max id (tie-break)
        $maxIdDql = $this->createQueryBuilder('p3')
            ->select('MAX(p3.id)')
            ->where('p3.sku = p.sku')
            ->andWhere("p3.version = ($maxVersionDql)")
            ->getDQL();

        $qb = $this->createQueryBuilder('p')
            ->andWhere("p.id = ($maxIdDql)")
            ->orderBy('p.sku', 'ASC');

        if ($skuFilter !== null && $skuFilter !== '') {
            $qb->andWhere('LOWER(p.sku) LIKE :sku')
               ->setParameter('sku', '%' . mb_strtolower($skuFilter) . '%');
        }

        if ($nameFilter !== null && $nameFilter !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :name')
               ->setParameter('name', '%' . mb_strtolower($nameFilter) . '%');
        }

        return $qb->getQuery()->getResult();
    }
    public function findLastFourProducts()
    {
        $results = $this->createQueryBuilder('p')
            ->where('p.image_urls IS NOT NULL')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

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
