<?php

namespace App\Controller;

use App\Repository\SessionRepository;
use App\Repository\TrainingTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendarController extends AbstractController
{
    #[Route('/calendar', name: 'app_calendar')]
    public function index(Request $request, SessionRepository $sessionRepo, TrainingTypeRepository $ttRepo): Response
    {
        $month = (int) $request->query->get('month', date('n'));
        $year  = (int) $request->query->get('year',  date('Y'));

        if ($month < 1)  { $month = 12; $year--; }
        if ($month > 12) { $month = 1;  $year++; }

        $start = new \DateTime("$year-$month-01");
        $end   = (clone $start)->modify('last day of this month')->setTime(23, 59, 59);

        $sessions = $sessionRepo->findByDateRange($start, $end);
        $byDay = [];
        foreach ($sessions as $session) {
            $byDay[(int) $session->getDate()->format('j')][] = $session;
        }

        $prevMonth = $month - 1; $prevYear = $year;
        if ($prevMonth < 1)  { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1; $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1;  $nextYear++; }

        $frMonths = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        $monthName = $frMonths[$month - 1] . ' ' . $year;

        return $this->render('calendar/index.html.twig', [
            'trainingTypes' => $ttRepo->findAllOrdered(),
            'year'         => $year,
            'month'        => $month,
            'monthName'    => $monthName,
            'daysInMonth'  => (int) $end->format('j'),
            'firstWeekday' => (int) $start->format('N'),
            'byDay'        => $byDay,
            'prevMonth'    => $prevMonth, 'prevYear' => $prevYear,
            'nextMonth'    => $nextMonth, 'nextYear' => $nextYear,
            'today'        => (int) date('j'),
            'currentMonth' => (int) date('n'),
            'currentYear'  => (int) date('Y'),
            'canEdit'      => $this->isGranted('ROLE_COACH'),
        ]);
    }
}
