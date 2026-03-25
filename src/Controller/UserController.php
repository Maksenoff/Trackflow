<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserEditType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users')]
class UserController extends AbstractController
{
    #[Route('', name: 'app_user_index')]
    public function index(UserRepository $repo): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $repo->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC']),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit')]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $currentRole = 'ROLE_ATHLETE';
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $currentRole = 'ROLE_ADMIN';
        } elseif (in_array('ROLE_COACH', $user->getRoles())) {
            $currentRole = 'ROLE_COACH';
        }

        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $allowed = ['ROLE_ADMIN', 'ROLE_COACH', 'ROLE_ATHLETE'];
            $submitted = $request->request->all('user_edit')['roles'] ?? '';
            $user->setRoles([in_array($submitted, $allowed) ? $submitted : 'ROLE_ATHLETE']);

            $em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user'        => $user,
            'form'        => $form,
            'currentRole' => $currentRole,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        // Empêcher l'admin de se supprimer lui-même
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_user_index');
        }

        if ($this->isCsrfTokenValid('delete-user' . $user->getId(), $request->getPayload()->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('app_user_index');
    }
}
