<?php

namespace App\Repository;

use App\Entity\ShopInfo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopInfo>
 *
 * @method ShopInfo|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShopInfo|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShopInfo[]    findAll()
 * @method ShopInfo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShopInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopInfo::class);
    }

    public function findWithTranslations(): ?ShopInfo
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.translations', 't')
            ->addSelect('t')
            ->getQuery()
            ->getOneOrNullResult();
    }

}
