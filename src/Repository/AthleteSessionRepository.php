<?php

namespace App\Repository;

use App\Entity\Athlete;
use App\Entity\AthleteSession;
use App\Entity\Session;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AthleteSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AthleteSession::class);
    }

    public function findByAthlete(Athlete $athlete): array
    {
        return $this->createQueryBuilder('asl')
            ->leftJoin('asl.session', 's')
            ->addSelect('s')
            ->andWhere('asl.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('s.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByAthleteAndSession(Athlete $athlete, Session $session): ?AthleteSession
    {
        return $this->createQueryBuilder('asl')
            ->andWhere('asl.athlete = :athlete')
            ->andWhere('asl.session = :session')
            ->setParameter('athlete', $athlete)
            ->setParameter('session', $session)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLoggedSessionIds(Athlete $athlete): array
    {
        $rows = $this->createQueryBuilder('asl')
            ->select('IDENTITY(asl.session) as sid')
            ->andWhere('asl.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->getQuery()
            ->getArrayResult();
        return array_column($rows, 'sid');
    }
}
