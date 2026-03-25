<?php

namespace App\Controller;

use App\Entity\Session;
use App\Form\SessionType;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sessions')]
class SessionController extends AbstractController
{
    #[Route('/{id}', name: 'app_session_show', requirements: ['id' => '\d+'])]
    public function show(Session $session): Response
    {
        return $this->render('session/show.html.twig', [
            'session' => $session,
        ]);
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/new', name: 'app_session_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $session = new Session();

        if ($date = $request->query->get('date')) {
            try { $session->setDate(new \DateTime($date)); } catch (\Exception) {}
        }

        $form = $this->createForm(SessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($session);
            $em->flush();
            $this->addFlash('success', 'Séance créée.');
            return $this->redirectToRoute('app_calendar', [
                'month' => $session->getDate()->format('n'),
                'year'  => $session->getDate()->format('Y'),
            ]);
        }

        return $this->render('session/new.html.twig', ['form' => $form]);
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/{id}/edit', name: 'app_session_edit')]
    public function edit(Session $session, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(SessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Séance mise à jour.');
            return $this->redirectToRoute('app_session_show', ['id' => $session->getId()]);
        }

        return $this->render('session/edit.html.twig', ['form' => $form, 'session' => $session]);
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/{id}/reschedule', name: 'app_session_reschedule', methods: ['POST'])]
    public function reschedule(Session $session, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        try {
            $session->setDate(new \DateTime($data['date'] ?? ''));
            $em->flush();
            return $this->json(['ok' => true]);
        } catch (\Exception) {
            return $this->json(['ok' => false], 400);
        }
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/{id}/delete', name: 'app_session_delete', methods: ['POST'])]
    public function delete(Session $session, Request $request, EntityManagerInterface $em): Response
    {
        $month = $session->getDate()->format('n');
        $year  = $session->getDate()->format('Y');

        if ($this->isCsrfTokenValid('delete-session' . $session->getId(), $request->getPayload()->get('_token'))) {
            $em->remove($session);
            $em->flush();
            $this->addFlash('success', 'Séance supprimée.');
        }
        return $this->redirectToRoute('app_calendar', ['month' => $month, 'year' => $year]);
    }
}
