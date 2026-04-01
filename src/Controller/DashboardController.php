<?php

namespace App\Controller;

use App\Entity\Performance;
use App\Repository\AthleteRepository;
use App\Repository\CompetitionRepository;
use App\Repository\PerformanceRepository;
use App\Repository\SessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        AthleteRepository $athleteRepo,
        SessionRepository $sessionRepo,
        PerformanceRepository $performanceRepo,
        CompetitionRepository $competitionRepo,
    ): Response {
        $upcomingSessions     = $sessionRepo->findUpcomingSessions(4);
        $upcomingCompetitions = $competitionRepo->findUpcomingCompetitions(4);

        $calcDiff = function (Performance $perf, ?Performance $prev): ?array {
            if (!$prev) return null;
            $curr  = (float) $perf->getValue();
            $prevVal = (float) $prev->getValue();
            if ($curr == $prevVal) return ['improved' => null, 'diff' => '='];

            $lowerIsBetter = in_array($perf->getUnit(), ['s', 'min:s']);
            $improved = $lowerIsBetter ? ($curr < $prevVal) : ($curr > $prevVal);
            $diff     = abs($curr - $prevVal);
            $sign     = $improved ? ($lowerIsBetter ? '-' : '+') : ($lowerIsBetter ? '+' : '-');

            $diffStr = $lowerIsBetter
                ? $sign . ($diff >= 60
                    ? sprintf('%d:%05.2f', (int)($diff / 60), fmod($diff, 60))
                    : number_format($diff, 2) . 's')
                : $sign . number_format($diff, 2) . ' ' . $perf->getUnit();

            return ['improved' => $improved, 'diff' => $diffStr];
        };

        $buildTrends = function (array $performances) use ($performanceRepo, $calcDiff): array {
            $trends = [];
            foreach ($performances as $perf) {
                $prev = $performanceRepo->findLastBefore(
                    $perf->getAthlete(), $perf->getDiscipline(), $perf->getRecordedAt(), $perf->getId()
                );
                $result = $calcDiff($perf, $prev);
                if ($result) $trends[$perf->getId()] = $result;
            }
            return $trends;
        };

        // Vue athlète : données personnelles uniquement
        if ($this->isGranted('ROLE_ATHLETE') && !$this->isGranted('ROLE_COACH')) {
            $linkedAthlete = $this->getUser()->getLinkedAthlete();
            if (!$linkedAthlete) {
                return $this->render('dashboard/athlete.html.twig', [
                    'athlete'               => null,
                    'recentPerformances'    => [],
                    'perfTrends'            => [],
                    'upcomingSessions'      => [],
                    'nextSession'           => null,
                    'upcomingCompetitions'  => $upcomingCompetitions,
                    'nextCompetition'       => $upcomingCompetitions[0] ?? null,
                ]);
            }

            $athletePerfs = $performanceRepo->findRecentByAthlete($linkedAthlete, 5);
            return $this->render('dashboard/athlete.html.twig', [
                'athlete'               => $linkedAthlete,
                'recentPerformances'    => $athletePerfs,
                'perfTrends'            => $buildTrends($athletePerfs),
                'upcomingSessions'      => $upcomingSessions,
                'nextSession'           => $upcomingSessions[0] ?? null,
                'upcomingCompetitions'  => $upcomingCompetitions,
                'nextCompetition'       => $upcomingCompetitions[0] ?? null,
            ]);
        }

        // Vue coach / admin
        $this->denyAccessUnlessGranted('ROLE_COACH');

        $coachPerfs    = $performanceRepo->findRecentPerformances(10);
        $linkedAthlete = $this->getUser()->getLinkedAthlete();
        $myPerfs       = $linkedAthlete ? $performanceRepo->findRecentByAthlete($linkedAthlete, 10) : [];
        $seasonBests   = $linkedAthlete ? $performanceRepo->findSeasonBestsByAthlete($linkedAthlete) : [];

        // Map id => isSB
        $sbIds = [];
        foreach ($seasonBests as $disc => $sbPerf) {
            $sbIds[$sbPerf->getId()] = true;
        }

        return $this->render('dashboard/coach.html.twig', [
            'totalAthletes'         => $athleteRepo->count([]),
            'recentPerformances'    => $coachPerfs,
            'perfTrends'            => $buildTrends($coachPerfs),
            'myPerformances'        => $myPerfs,
            'myPerfTrends'          => $buildTrends($myPerfs),
            'myPerfSBIds'           => $sbIds,
            'linkedAthlete'         => $linkedAthlete,
            'upcomingSessions'      => $upcomingSessions,
            'nextSession'           => $upcomingSessions[0] ?? null,
            'upcomingCompetitions'  => $upcomingCompetitions,
            'nextCompetition'       => $upcomingCompetitions[0] ?? null,
        ]);
    }
}
