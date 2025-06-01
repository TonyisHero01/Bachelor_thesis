<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Find user’s orders
     */
    public function findOrdersByCustomer(int $customerId)
    {
        return $this->createQueryBuilder('o')
            ->where('o.customer = :customerId')
            ->setParameter('customerId', $customerId)
            ->orderBy('o.orderCreatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find the specified order (ensure the order belongs to the current user)
     */
    public function findOrderByIdAndCustomer(int $orderId, int $customerId)
    {
        return $this->createQueryBuilder('o')
            ->where('o.id = :orderId')
            ->andWhere('o.customer = :customerId')
            ->setParameter('orderId', $orderId)
            ->setParameter('customerId', $customerId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllOrders()
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.orderCreatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}