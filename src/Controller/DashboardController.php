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

        // Vue athlète : données personnelles uniquement
        if ($this->isGranted('ROLE_ATHLETE') && !$this->isGranted('ROLE_COACH')) {
            $linkedAthlete = $this->getUser()->getLinkedAthlete();
            if (!$linkedAthlete) {
                return $this->render('dashboard/athlete.html.twig', [
                    'athlete'               => null,
                    'recentPerformances'    => [],
                    'upcomingSessions'      => [],
                    'nextSession'           => null,
                    'upcomingCompetitions'  => $upcomingCompetitions,
                    'nextCompetition'       => $upcomingCompetitions[0] ?? null,
                ]);
            }

            return $this->render('dashboard/athlete.html.twig', [
                'athlete'               => $linkedAthlete,
                'recentPerformances'    => $performanceRepo->findRecentByAthlete($linkedAthlete, 5),
                'upcomingSessions'      => $upcomingSessions,
                'nextSession'           => $upcomingSessions[0] ?? null,
                'upcomingCompetitions'  => $upcomingCompetitions,
                'nextCompetition'       => $upcomingCompetitions[0] ?? null,
            ]);
        }

        // Vue coach / admin
        $this->denyAccessUnlessGranted('ROLE_COACH');

        return $this->render('dashboard/coach.html.twig', [
            'totalAthletes'         => $athleteRepo->count([]),
            'recentPerformances'    => $performanceRepo->findRecentPerformances(10),
            'upcomingSessions'      => $upcomingSessions,
            'nextSession'           => $upcomingSessions[0] ?? null,
            'upcomingCompetitions'  => $upcomingCompetitions,
            'nextCompetition'       => $upcomingCompetitions[0] ?? null,
        ]);
    }
}
