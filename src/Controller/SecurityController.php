<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/', name: 'app_root')]
    #[Route('/home', name: 'app_home')]
    public function home(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user && $user->isAthlete() && $user->getLinkedAthlete()) {
            return $this->redirectToRoute('app_athlete_show', ['id' => $user->getLinkedAthlete()->getId()]);
        }
        if ($user && $user->isAthlete()) {
            return $this->redirectToRoute('app_athlete_index');
        }
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Handled by Symfony's firewall
    }
}
