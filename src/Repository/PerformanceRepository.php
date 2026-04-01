<?php

namespace App\Repository;

use App\Entity\Athlete;
use App\Entity\Performance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
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

    public function findRecentByAthlete(Athlete $athlete, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('p.recordedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findCompetitionSparkline(Athlete $athlete, string $discipline, int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.athlete = :athlete')
            ->andWhere('p.discipline = :discipline')
            ->andWhere('p.isCompetition = true')
            ->setParameter('athlete', $athlete)
            ->setParameter('discipline', $discipline)
            ->orderBy('p.recordedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLastBefore(Athlete $athlete, string $discipline, \DateTimeInterface $before, int $excludeId = 0): ?Performance
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.athlete = :athlete')
            ->andWhere('p.discipline = :discipline')
            ->andWhere('p.recordedAt <= :before')
            ->andWhere('p.id != :excludeId')
            ->setParameter('athlete', $athlete)
            ->setParameter('discipline', $discipline)
            ->setParameter('before', $before, Types::DATE_MUTABLE)
            ->setParameter('excludeId', $excludeId)
            ->orderBy('p.recordedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastCompetitionBefore(Athlete $athlete, string $discipline, \DateTimeInterface $before): ?Performance
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.athlete = :athlete')
            ->andWhere('p.discipline = :discipline')
            ->andWhere('p.isCompetition = true')
            ->andWhere('p.recordedAt < :before')
            ->setParameter('athlete', $athlete)
            ->setParameter('discipline', $discipline)
            ->setParameter('before', $before)
            ->orderBy('p.recordedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne le meilleur résultat de la saison athlétique en cours (sept–août)
     * pour chaque discipline de l'athlète, sous la forme [discipline => Performance].
     */
    public function findSeasonBestsByAthlete(Athlete $athlete): array
    {
        $now   = new \DateTime();
        $month = (int) $now->format('n');
        $year  = (int) $now->format('Y');
        $seasonStart = new \DateTime(($month >= 9 ? $year : $year - 1) . '-09-01');

        $perfs = $this->createQueryBuilder('p')
            ->andWhere('p.athlete = :athlete')
            ->andWhere('p.recordedAt >= :seasonStart')
            ->setParameter('athlete', $athlete)
            ->setParameter('seasonStart', $seasonStart)
            ->orderBy('p.recordedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Unité détermine si lower = better
        $lowerIsBetter = fn(string $unit): bool => in_array($unit, ['s', 'min:s']);

        $bests = [];
        foreach ($perfs as $perf) {
            $disc = $perf->getDiscipline();
            if (!isset($bests[$disc])) {
                $bests[$disc] = $perf;
                continue;
            }
            $curr = (float) $perf->getValue();
            $best = (float) $bests[$disc]->getValue();
            $improved = $lowerIsBetter($perf->getUnit()) ? $curr < $best : $curr > $best;
            if ($improved) {
                $bests[$disc] = $perf;
            }
        }

        return $bests; // [discipline => Performance]
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
