<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\CustomerProductViewLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerProductViewLog>
 */
class CustomerProductViewLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerProductViewLog::class);
    }

    /**
     * Returns recent viewed SKUs for a customer.
     */
    public function findRecentViewedSkusByCustomer(
        Customer $customer,
        int $limit = 20
    ): array {
        $rows = $this->createQueryBuilder('v')
            ->select('v.sku AS sku')
            ->where('v.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('v.viewedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $skus = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku !== '' && !in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    /**
     * Returns most viewed products globally.
     */
    public function findMostViewedProducts(
        int $limit = 20
    ): array {
        return $this->createQueryBuilder('v')
            ->select('v.sku AS sku, COUNT(v.id) AS views')
            ->groupBy('v.sku')
            ->orderBy('views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns recently viewed products for session-based guests.
     */
    public function findRecentViewedSkusBySession(
        string $sessionId,
        int $limit = 20
    ): array {
        $rows = $this->createQueryBuilder('v')
            ->select('v.sku AS sku')
            ->where('v.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('v.viewedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $skus = [];

        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));

            if ($sku !== '' && !in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    /**
     * Returns total view count for a SKU.
     */
    public function countViewsForSku(string $sku): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.sku = :sku')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns recently viewed logs.
     */
    public function findRecentLogs(
        int $limit = 100
    ): array {
        return $this->createQueryBuilder('v')
            ->orderBy('v.viewedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}