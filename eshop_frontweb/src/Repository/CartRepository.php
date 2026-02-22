<?php
namespace App\Repository;

use App\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }
    public function getTotalQuantityByCustomer($customer): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('SUM(c.quantity)')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }
}