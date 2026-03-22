<?php

namespace App\Controller;

use App\Entity\Athlete;
use App\Entity\Performance;
use App\Form\PerformanceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/athletes/{athleteId}/performances')]
class PerformanceController extends AbstractController
{
    #[Route('/new', name: 'app_performance_new')]
    public function new(int $athleteId, Request $request, EntityManagerInterface $em): Response
    {
        $athlete = $em->getRepository(Athlete::class)->find($athleteId);
        if (!$athlete) throw $this->createNotFoundException();

        $performance = new Performance();
        $performance->setAthlete($athlete);
        // Pre-fill with athlete's main discipline
        $performance->setDiscipline($athlete->getDiscipline());

        $form = $this->createForm(PerformanceType::class, $performance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($performance);
            $em->flush();
            $this->addFlash('success', 'Performance enregistrée.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athlete->getId()]);
        }

        return $this->render('performance/new.html.twig', [
            'form' => $form,
            'athlete' => $athlete,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_performance_edit')]
    public function edit(int $athleteId, Performance $performance, Request $request, EntityManagerInterface $em): Response
    {
        $athlete = $em->getRepository(Athlete::class)->find($athleteId);
        if (!$athlete || $performance->getAthlete() !== $athlete) throw $this->createNotFoundException();

        $form = $this->createForm(PerformanceType::class, $performance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Performance mise à jour.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athlete->getId()]);
        }

        return $this->render('performance/edit.html.twig', [
            'form' => $form,
            'athlete' => $athlete,
            'performance' => $performance,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_performance_delete', methods: ['POST'])]
    public function delete(int $athleteId, Performance $performance, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-perf' . $performance->getId(), $request->getPayload()->get('_token'))) {
            $em->remove($performance);
            $em->flush();
            $this->addFlash('success', 'Performance supprimée.');
        }
        return $this->redirectToRoute('app_athlete_show', ['id' => $athleteId]);
    }
}
