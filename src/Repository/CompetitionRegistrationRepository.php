<?php

namespace App\Repository;

use App\Entity\Athlete;
use App\Entity\Competition;
use App\Entity\CompetitionRegistration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CompetitionRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompetitionRegistration::class);
    }

    public function findByCompetition(Competition $competition): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.athlete', 'a')
            ->addSelect('a')
            ->andWhere('r.competition = :competition')
            ->setParameter('competition', $competition)
            ->orderBy('a.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByAthleteAndCompetition(Athlete $athlete, Competition $competition): ?CompetitionRegistration
    {
        return $this->findOneBy(['athlete' => $athlete, 'competition' => $competition]);
    }
}
