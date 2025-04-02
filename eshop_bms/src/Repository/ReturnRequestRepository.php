<?php

namespace App\Repository;

use App\Entity\ReturnRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReturnRequest>
 *
 * @method ReturnRequest|null find($id, $lockMode = null, $lockVersion = null)
 * @method ReturnRequest|null findOneBy(array $criteria, array $orderBy = null)
 * @method ReturnRequest[]    findAll()
 * @method ReturnRequest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReturnRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReturnRequest::class);
    }

//    /**
//     * @return ReturnRequest[] Returns an array of ReturnRequest objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ReturnRequest
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
