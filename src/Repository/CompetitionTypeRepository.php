<?php

namespace App\Repository;

use App\Entity\CompetitionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompetitionTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompetitionType::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('ct')
            ->orderBy('ct.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
