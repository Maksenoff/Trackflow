<?php

namespace App\Controller;

use App\Entity\Athlete;
use App\Entity\User;
use App\Repository\AthleteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserAdminController extends AbstractController
{
    #[Route('', name: 'app_user_index')]
    public function index(UserRepository $userRepo, AthleteRepository $athleteRepo): Response
    {
        return $this->render('user/index.html.twig', [
            'users'    => $userRepo->findBy([], ['lastName' => 'ASC']),
            'athletes' => $athleteRepo->findAllOrderedByName(),
        ]);
    }

    #[Route('/{id}/link', name: 'app_user_link_athlete', methods: ['POST'])]
    public function linkAthlete(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        AthleteRepository $athleteRepo
    ): Response {
        if (!$this->isCsrfTokenValid('link-user' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $athleteId = $request->request->get('athlete_id');

        // Unlink old user that was linked to this athlete
        if ($athleteId) {
            $athlete = $athleteRepo->find((int) $athleteId);
            if (!$athlete) {
                $this->addFlash('error', 'Athlète introuvable.');
                return $this->redirectToRoute('app_user_index');
            }

            // Remove link from any other user already linked to this athlete
            $existingUser = $em->getRepository(User::class)->findOneBy(['linkedAthlete' => $athlete]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                $existingUser->setLinkedAthlete(null);
            }

            $user->setLinkedAthlete($athlete);
            $this->addFlash('success', $user->getFullName() . ' lié à ' . $athlete->getFullName() . '.');
        } else {
            $user->setLinkedAthlete(null);
            $this->addFlash('success', 'Lien supprimé pour ' . $user->getFullName() . '.');
        }

        $em->flush();
        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/role', name: 'app_user_change_role', methods: ['POST'])]
    public function changeRole(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('role-user' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $role = $request->request->get('role');
        $allowed = ['ROLE_ATHLETE', 'ROLE_COACH', 'ROLE_ADMIN'];
        if (!in_array($role, $allowed)) {
            $this->addFlash('error', 'Rôle invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        // Prevent removing your own admin role
        if ($user->getId() === $this->getUser()->getId() && $role !== 'ROLE_ADMIN') {
            $this->addFlash('error', 'Vous ne pouvez pas modifier votre propre rôle.');
            return $this->redirectToRoute('app_user_index');
        }

        $user->setRoles([$role]);
        $em->flush();
        $this->addFlash('success', 'Rôle mis à jour pour ' . $user->getFullName() . '.');
        return $this->redirectToRoute('app_user_index');
    }
}
