<?php

namespace App\Repository;

use App\Entity\SearchRelevanceConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SearchRelevanceConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SearchRelevanceConfig::class);
    }

    public function findActiveConfig(): ?SearchRelevanceConfig
    {
        return $this->findOneBy(['active' => true], ['id' => 'DESC']);
    }
}