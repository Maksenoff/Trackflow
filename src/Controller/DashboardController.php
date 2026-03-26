<?php

namespace App\Controller;

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

        // Tendance comparée à la dernière perf en compétition
        $buildTrends = function (array $performances) use ($performanceRepo): array {
            $trends = [];
            foreach ($performances as $perf) {
                $prev = $performanceRepo->findLastCompetitionBefore(
                    $perf->getAthlete(),
                    $perf->getDiscipline(),
                    $perf->getRecordedAt()
                );
                if (!$prev) continue;

                $curr          = (float) $perf->getValue();
                $prevVal       = (float) $prev->getValue();
                if ($curr == $prevVal) continue;

                $lowerIsBetter = in_array($perf->getUnit(), ['s', 'min:s']);
                $improved      = $lowerIsBetter ? ($curr < $prevVal) : ($curr > $prevVal);
                $diff          = abs($curr - $prevVal);

                // Signe : pour le temps on affiche -diff si amélioration (on enlève du temps)
                // Pour les autres disciplines on affiche +diff si amélioration
                $sign = $improved ? '-' : '+';
                if (!$lowerIsBetter) $sign = $improved ? '+' : '-';

                if ($lowerIsBetter) {
                    $diffStr = $sign . ($diff >= 60
                        ? sprintf('%d:%05.2f', (int)($diff / 60), fmod($diff, 60))
                        : number_format($diff, 2) . 's');
                } else {
                    $diffStr = $sign . number_format($diff, 2) . ' ' . $perf->getUnit();
                }

                $trends[$perf->getId()] = [
                    'improved' => $improved,
                    'diff'     => $diffStr,
                ];
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

        $coachPerfs = $performanceRepo->findRecentPerformances(10);
        return $this->render('dashboard/coach.html.twig', [
            'totalAthletes'         => $athleteRepo->count([]),
            'recentPerformances'    => $coachPerfs,
            'perfTrends'            => $buildTrends($coachPerfs),
            'upcomingSessions'      => $upcomingSessions,
            'nextSession'           => $upcomingSessions[0] ?? null,
            'upcomingCompetitions'  => $upcomingCompetitions,
            'nextCompetition'       => $upcomingCompetitions[0] ?? null,
        ]);
    }
}
