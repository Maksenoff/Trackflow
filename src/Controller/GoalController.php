<?php

namespace App\Controller;

use App\Entity\Athlete;
use App\Entity\Goal;
use App\Form\GoalType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/athletes/{athleteId}/goals')]
class GoalController extends AbstractController
{
    #[Route('/new', name: 'app_goal_new')]
    public function new(int $athleteId, Request $request, EntityManagerInterface $em): Response
    {
        $athlete = $em->getRepository(Athlete::class)->find($athleteId);
        if (!$athlete) throw $this->createNotFoundException();

        $goal = new Goal();
        $goal->setAthlete($athlete);
        $form = $this->createForm(GoalType::class, $goal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($goal);
            $em->flush();
            $this->addFlash('success', 'Objectif créé.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athlete->getId()]);
        }

        return $this->render('goal/new.html.twig', [
            'form' => $form,
            'athlete' => $athlete,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_goal_edit')]
    public function edit(int $athleteId, Goal $goal, Request $request, EntityManagerInterface $em): Response
    {
        $athlete = $em->getRepository(Athlete::class)->find($athleteId);
        if (!$athlete || $goal->getAthlete() !== $athlete) throw $this->createNotFoundException();

        $form = $this->createForm(GoalType::class, $goal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Objectif mis à jour.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athlete->getId()]);
        }

        return $this->render('goal/edit.html.twig', [
            'form' => $form,
            'athlete' => $athlete,
            'goal' => $goal,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_goal_delete', methods: ['POST'])]
    public function delete(int $athleteId, Goal $goal, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-goal' . $goal->getId(), $request->getPayload()->get('_token'))) {
            $em->remove($goal);
            $em->flush();
            if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse(['success' => true]);
            }
            $this->addFlash('success', 'Objectif supprimé.');
        }
        return $this->redirectToRoute('app_athlete_show', ['id' => $athleteId]);
    }

    #[Route('/{id}/toggle', name: 'app_goal_toggle', methods: ['POST'])]
    public function toggle(int $athleteId, Goal $goal, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('toggle-goal' . $goal->getId(), $request->getPayload()->get('_token'))) {
            $goal->setStatus($goal->isAchieved() ? 'in_progress' : 'achieved');
            $em->flush();
            if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                return new JsonResponse(['achieved' => $goal->isAchieved()]);
            }
        }
        return $this->redirectToRoute('app_athlete_show', ['id' => $athleteId]);
    }
}
