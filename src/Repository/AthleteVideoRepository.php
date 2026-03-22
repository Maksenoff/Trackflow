<?php

namespace App\Repository;

use App\Entity\Athlete;
use App\Entity\AthleteVideo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AthleteVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AthleteVideo::class);
    }

    /**
     * @return AthleteVideo[][]  keyed by discipline ('' for uncategorized)
     */
    public function findByAthleteGroupedByDiscipline(Athlete $athlete): array
    {
        $videos = $this->createQueryBuilder('v')
            ->where('v.athlete = :athlete')
            ->setParameter('athlete', $athlete)
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($videos as $video) {
            $key = $video->getDiscipline() ?? '';
            $grouped[$key][] = $video;
        }

        return $grouped;
    }
}
