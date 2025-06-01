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
        $subQuery = $this->createQueryBuilder('p2')
            ->select('MAX(p2.version)')
            ->where('p2.sku = p.sku')
            ->getDQL();

        return $this->createQueryBuilder('p')
            ->where("p.version = ($subQuery)")
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
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
