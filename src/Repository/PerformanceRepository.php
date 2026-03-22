<?php

namespace App\Repository;

use App\Entity\Athlete;
use App\Entity\Performance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PerformanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Performance::class);
    }

    public function findByAthleteAndDiscipline(Athlete $athlete, string $discipline): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.athlete = :athlete')
            ->andWhere('p.discipline = :discipline')
            ->setParameter('athlete', $athlete)
            ->setParameter('discipline', $discipline)
            ->orderBy('p.recordedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPersonalBests(Athlete $athlete): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.athlete = :athlete')
            ->andWhere('p.isPersonalBest = true')
            ->setParameter('athlete', $athlete)
            ->orderBy('p.discipline', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentPerformances(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.athlete', 'a')
            ->addSelect('a')
            ->orderBy('p.recordedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByAthleteGroupedByDiscipline(Athlete $athlete): array
    {
        $performances = $this->createQueryBuilder('p')
            ->andWhere('p.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('p.recordedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($performances as $perf) {
            $grouped[$perf->getDiscipline()][] = $perf;
        }
        return $grouped;
    }
}
