<?php

namespace App\Repository;

use App\Entity\Athlete;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AthleteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Athlete::class);
    }

    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.lastName', 'ASC')
            ->addOrderBy('a.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDiscipline(string $discipline): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.discipline = :discipline')
            ->setParameter('discipline', $discipline)
            ->orderBy('a.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
