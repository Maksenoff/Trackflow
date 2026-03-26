<?php

namespace App\Controller;

use App\Entity\CompetitionRegistration;
use App\Repository\CompetitionRegistrationRepository;
use App\Repository\CompetitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/competitions/{id}/inscription')]
class CompetitionRegistrationController extends AbstractController
{
    #[Route('', name: 'app_competition_register', methods: ['POST'])]
    public function register(
        int $id,
        Request $request,
        CompetitionRepository $competitionRepo,
        CompetitionRegistrationRepository $registrationRepo,
        EntityManagerInterface $em,
    ): Response {
        $competition = $competitionRepo->find($id);
        if (!$competition) throw $this->createNotFoundException();

        $athlete = $this->getUser()->getLinkedAthlete();
        if (!$athlete) {
            $this->addFlash('error', 'Aucun profil athlète lié à votre compte.');
            return $this->redirectToRoute('app_competition_show', ['id' => $id]);
        }

        if (!$this->isCsrfTokenValid('register-competition-' . $id, $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $disciplines = $request->getPayload()->all('disciplines') ?? [];
        $disciplines = array_values(array_filter($disciplines));

        if (empty($disciplines)) {
            $this->addFlash('error', 'Sélectionne au moins une discipline.');
            return $this->redirectToRoute('app_competition_show', ['id' => $id]);
        }

        $registration = $registrationRepo->findByAthleteAndCompetition($athlete, $competition)
            ?? (new CompetitionRegistration())->setAthlete($athlete)->setCompetition($competition);

        $registration->setDisciplines($disciplines);
        $em->persist($registration);
        $em->flush();

        $this->addFlash('success', 'Inscription enregistrée.');
        return $this->redirectToRoute('app_competition_show', ['id' => $id]);
    }

    #[Route('/annuler/{registrationId}', name: 'app_competition_unregister_any', methods: ['POST'])]
    public function unregisterAny(
        int $id,
        int $registrationId,
        Request $request,
        CompetitionRegistrationRepository $registrationRepo,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        if (!$this->isCsrfTokenValid('unregister-any-' . $registrationId, $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $registration = $registrationRepo->find($registrationId);
        if ($registration && $registration->getCompetition()->getId() === $id) {
            $em->remove($registration);
            $em->flush();
            $this->addFlash('success', 'Inscription annulée.');
        }

        return $this->redirectToRoute('app_competition_show', ['id' => $id]);
    }

    #[Route('/annuler', name: 'app_competition_unregister', methods: ['POST'])]
    public function unregister(
        int $id,
        Request $request,
        CompetitionRepository $competitionRepo,
        CompetitionRegistrationRepository $registrationRepo,
        EntityManagerInterface $em,
    ): Response {
        $competition = $competitionRepo->find($id);
        if (!$competition) throw $this->createNotFoundException();

        $athlete = $this->getUser()->getLinkedAthlete();
        if (!$athlete) {
            return $this->redirectToRoute('app_competition_show', ['id' => $id]);
        }

        if (!$this->isCsrfTokenValid('unregister-competition-' . $id, $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $registration = $registrationRepo->findByAthleteAndCompetition($athlete, $competition);
        if ($registration) {
            $em->remove($registration);
            $em->flush();
            $this->addFlash('success', 'Inscription annulée.');
        }

        return $this->redirectToRoute('app_competition_show', ['id' => $id]);
    }
}
