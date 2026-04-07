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
            return $this->ajaxOrFlash($request, 'error', 'Aucun profil athlète lié.', $id);
        }

        if (!$this->isCsrfTokenValid('register-competition-' . $id, $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $disciplines = array_values(array_filter($request->getPayload()->all('disciplines') ?? []));
        if (empty($disciplines)) {
            return $this->ajaxOrFlash($request, 'error', 'Sélectionne au moins une discipline.', $id);
        }

        $registration = $registrationRepo->findByAthleteAndCompetition($athlete, $competition)
            ?? (new CompetitionRegistration())->setAthlete($athlete)->setCompetition($competition);

        $registration->setDisciplines($disciplines);
        $em->persist($registration);
        $em->flush();

        if ($this->isAjax($request)) {
            return $this->json($this->buildPayload(
                $registrationRepo->findByCompetition($competition),
                $registration,
                $this->isGranted('ROLE_COACH'),
                $this->container->get('security.csrf.token_manager'),
            ));
        }

        $this->addFlash('success', 'Inscription enregistrée.');
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
        }

        if ($this->isAjax($request)) {
            return $this->json($this->buildPayload(
                $registrationRepo->findByCompetition($competition),
                null,
                $this->isGranted('ROLE_COACH'),
                $this->container->get('security.csrf.token_manager'),
            ));
        }

        $this->addFlash('success', 'Inscription annulée.');
        return $this->redirectToRoute('app_competition_show', ['id' => $id]);
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/{registrationId}/update', name: 'app_competition_update_any', methods: ['POST'])]
    public function updateAny(
        int $id,
        int $registrationId,
        Request $request,
        CompetitionRepository $competitionRepo,
        CompetitionRegistrationRepository $registrationRepo,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('update-any-' . $registrationId, $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $competition = $competitionRepo->find($id);
        if (!$competition) throw $this->createNotFoundException();

        $registration = $registrationRepo->find($registrationId);
        if (!$registration || $registration->getCompetition()->getId() !== $id) {
            return $this->json(['ok' => false], 404);
        }

        $disciplines = array_values(array_filter($request->getPayload()->all('disciplines') ?? []));
        if (empty($disciplines)) {
            return $this->json(['ok' => false, 'error' => 'Sélectionne au moins une discipline.'], 400);
        }

        $registration->setDisciplines($disciplines);
        $em->flush();

        $linkedAthlete  = $this->getUser()->getLinkedAthlete();
        $myRegistration = $linkedAthlete
            ? $registrationRepo->findByAthleteAndCompetition($linkedAthlete, $competition)
            : null;

        return $this->json($this->buildPayload(
            $registrationRepo->findByCompetition($competition),
            $myRegistration,
            true,
            $this->container->get('security.csrf.token_manager'),
        ));
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/{registrationId}/toggle-ffa', name: 'app_competition_toggle_ffa', methods: ['POST'])]
    public function toggleFfa(
        int $id,
        int $registrationId,
        Request $request,
        CompetitionRepository $competitionRepo,
        CompetitionRegistrationRepository $registrationRepo,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('ffa-toggle-' . $registrationId, $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $competition  = $competitionRepo->find($id);
        if (!$competition) throw $this->createNotFoundException();

        $registration = $registrationRepo->find($registrationId);
        if (!$registration || $registration->getCompetition()->getId() !== $id) {
            return $this->json(['ok' => false], 404);
        }

        $registration->setFfaRegistered(!$registration->isFfaRegistered());
        $em->flush();

        $linkedAthlete  = $this->getUser()->getLinkedAthlete();
        $myRegistration = $linkedAthlete
            ? $registrationRepo->findByAthleteAndCompetition($linkedAthlete, $competition)
            : null;

        return $this->json($this->buildPayload(
            $registrationRepo->findByCompetition($competition),
            $myRegistration,
            true,
            $this->container->get('security.csrf.token_manager'),
        ));
    }

    #[Route('/annuler/{registrationId}', name: 'app_competition_unregister_any', methods: ['POST'])]
    public function unregisterAny(
        int $id,
        int $registrationId,
        Request $request,
        CompetitionRepository $competitionRepo,
        CompetitionRegistrationRepository $registrationRepo,
        EntityManagerInterface $em,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_COACH');

        if (!$this->isCsrfTokenValid('unregister-any-' . $registrationId, $request->getPayload()->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $competition = $competitionRepo->find($id);
        if (!$competition) throw $this->createNotFoundException();

        $registration = $registrationRepo->find($registrationId);
        if ($registration && $registration->getCompetition()->getId() === $id) {
            $em->remove($registration);
            $em->flush();
        }

        $linkedAthlete  = $this->getUser()->getLinkedAthlete();
        $myRegistration = $linkedAthlete
            ? $registrationRepo->findByAthleteAndCompetition($linkedAthlete, $competition)
            : null;

        if ($this->isAjax($request)) {
            return $this->json($this->buildPayload(
                $registrationRepo->findByCompetition($competition),
                $myRegistration,
                true,
                $this->container->get('security.csrf.token_manager'),
            ));
        }

        $this->addFlash('success', 'Inscription annulée.');
        return $this->redirectToRoute('app_competition_show', ['id' => $id]);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function isAjax(Request $request): bool
    {
        return $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
    }

    private function ajaxOrFlash(Request $request, string $type, string $msg, int $id): Response
    {
        if ($this->isAjax($request)) {
            return $this->json(['ok' => false, 'error' => $msg], 400);
        }
        $this->addFlash($type, $msg);
        return $this->redirectToRoute('app_competition_show', ['id' => $id]);
    }

    private function buildPayload(array $registrations, ?CompetitionRegistration $myReg, bool $isCoach, $csrf): array
    {
        return [
            'ok'             => true,
            'myRegistration' => $myReg ? ['id' => $myReg->getId(), 'disciplines' => $myReg->getDisciplines()] : null,
            'registrations'  => array_map(fn($reg) => [
                'id'            => $reg->getId(),
                'athleteName'   => $reg->getAthlete()->getFullName(),
                'athletePhoto'  => $reg->getAthlete()->getPhoto(),
                'licenseNumber' => $reg->getAthlete()->getLicenseNumber(),
                'disciplines'   => $reg->getDisciplines(),
                'ffaRegistered' => $reg->isFfaRegistered(),
                'isOwn'         => $myReg && $myReg->getId() === $reg->getId(),
                'canRemove'     => ($myReg && $myReg->getId() === $reg->getId()) || $isCoach,
                'canManage'     => $isCoach,
                'unregToken'    => $csrf->getToken('unregister-any-' . $reg->getId())->getValue(),
                'ffaToken'      => $csrf->getToken('ffa-toggle-' . $reg->getId())->getValue(),
                'updateToken'   => $csrf->getToken('update-any-' . $reg->getId())->getValue(),
            ], $registrations),
        ];
    }
}
