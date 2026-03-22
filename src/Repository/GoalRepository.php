<?php

namespace App\Repository;

use App\Entity\Athlete;
use App\Entity\Goal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Goal::class);
    }

    public function findByAthlete(Athlete $athlete): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('g.status', 'ASC')
            ->addOrderBy('g.deadline', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
