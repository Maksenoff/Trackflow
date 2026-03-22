<?php

namespace App\Controller;

use App\Repository\AthleteRepository;
use App\Repository\GoalRepository;
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
        GoalRepository $goalRepo,
    ): Response {
        $athletes = $athleteRepo->findAllOrderedByName();
        $recentPerformances = $performanceRepo->findRecentPerformances(8);
        $sessionsThisMonth = $sessionRepo->countThisMonth();
        $goalsAchieved = $goalRepo->countByStatus('achieved');
        $goalsInProgress = $goalRepo->countByStatus('in_progress');

        return $this->render('dashboard/index.html.twig', [
            'athletes' => $athletes,
            'recentPerformances' => $recentPerformances,
            'sessionsThisMonth' => $sessionsThisMonth,
            'goalsAchieved' => $goalsAchieved,
            'goalsInProgress' => $goalsInProgress,
            'totalAthletes' => count($athletes),
        ]);
    }
}
