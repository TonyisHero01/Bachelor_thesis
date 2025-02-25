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
     * 查找用户的订单
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
     * 查找指定订单（确保该订单属于当前用户）
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
            ->orderBy('o.orderCreatedAt', 'DESC') // 按创建时间倒序排列
            ->getQuery()
            ->getResult();
    }
}