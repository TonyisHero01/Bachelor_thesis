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
}
