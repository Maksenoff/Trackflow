<?php

namespace App\Controller;

use App\Entity\Athlete;
use App\Entity\AthleteSession;
use App\Entity\Session;
use App\Form\AthleteSessionType;
use App\Repository\AthleteSessionRepository;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/athletes/{athleteId}/log')]
class AthleteSessionController extends AbstractController
{
    /**
     * Show past sessions not yet logged by this athlete + a form to log them.
     */
    #[Route('/new/{sessionId}', name: 'app_athlete_session_log')]
    public function log(
        int $athleteId,
        int $sessionId,
        Request $request,
        EntityManagerInterface $em,
        AthleteSessionRepository $asRepo
    ): Response {
        if (!$this->isGranted('ROLE_COACH') && $this->getUser()->getLinkedAthlete()?->getId() !== $athleteId) {
            throw $this->createAccessDeniedException();
        }

        $athlete = $em->getRepository(Athlete::class)->find($athleteId);
        $session = $em->getRepository(Session::class)->find($sessionId);

        if (!$athlete || !$session) {
            throw $this->createNotFoundException();
        }

        // Check if already logged
        $existing = $asRepo->findOneByAthleteAndSession($athlete, $session);
        if ($existing) {
            $this->addFlash('info', 'Cette séance est déjà enregistrée.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athleteId]);
        }

        // Only allow past sessions
        if (!$session->isPast()) {
            $this->addFlash('error', 'Vous ne pouvez pas encore enregistrer une séance future.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athleteId]);
        }

        $athleteSession = new AthleteSession();
        $athleteSession->setAthlete($athlete)->setSession($session);

        $form = $this->createForm(AthleteSessionType::class, $athleteSession);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($athleteSession);
            $em->flush();
            $this->addFlash('success', 'Séance enregistrée avec ton ressenti.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athleteId]);
        }

        return $this->render('athlete_session/log.html.twig', [
            'form'    => $form,
            'athlete' => $athlete,
            'session' => $session,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_athlete_session_edit')]
    public function edit(
        int $athleteId,
        AthleteSession $athleteSession,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isGranted('ROLE_COACH') && $this->getUser()->getLinkedAthlete()?->getId() !== $athleteId) {
            throw $this->createAccessDeniedException();
        }

        $athlete = $em->getRepository(Athlete::class)->find($athleteId);
        if (!$athlete || $athleteSession->getAthlete() !== $athlete) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AthleteSessionType::class, $athleteSession);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Ressenti mis à jour.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athleteId]);
        }

        return $this->render('athlete_session/edit.html.twig', [
            'form'           => $form,
            'athlete'        => $athlete,
            'athleteSession' => $athleteSession,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_athlete_session_delete', methods: ['POST'])]
    public function delete(
        int $athleteId,
        AthleteSession $athleteSession,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isGranted('ROLE_COACH') && $this->getUser()->getLinkedAthlete()?->getId() !== $athleteId) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete-as' . $athleteSession->getId(), $request->getPayload()->get('_token'))) {
            $em->remove($athleteSession);
            $em->flush();
            $this->addFlash('success', 'Ressenti supprimé.');
        }
        return $this->redirectToRoute('app_athlete_show', ['id' => $athleteId]);
    }
}
