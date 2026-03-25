<?php

namespace App\Controller;

use App\Entity\CompetitionType;
use App\Form\CompetitionTypeType;
use App\Repository\CompetitionTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/settings/competition-types')]
class CompetitionTypeController extends AbstractController
{
    #[Route('', name: 'app_competition_type_index')]
    public function index(CompetitionTypeRepository $repo, Request $request, EntityManagerInterface $em): Response
    {
        $newType = new CompetitionType();
        $form    = $this->createForm(CompetitionTypeType::class, $newType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($newType);
            $em->flush();
            $this->addFlash('success', 'Type "' . $newType->getName() . '" créé.');
            return $this->redirectToRoute('app_competition_type_index');
        }

        return $this->render('competition_type/index.html.twig', [
            'types' => $repo->findAllOrdered(),
            'form'  => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_competition_type_delete', methods: ['POST'])]
    public function delete(CompetitionType $type, Request $request, EntityManagerInterface $em): Response
    {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if ($this->isCsrfTokenValid('delete-ct' . $type->getId(), $request->getPayload()->get('_token'))) {
            foreach ($type->getCompetitions() as $competition) {
                $competition->setCompetitionType(null);
            }
            $em->remove($type);
            $em->flush();

            if ($isAjax) {
                return $this->json(['ok' => true]);
            }
            $this->addFlash('success', 'Type supprimé.');
        } elseif ($isAjax) {
            return $this->json(['ok' => false], 403);
        }

        return $this->redirectToRoute('app_competition_type_index');
    }

    #[Route('/{id}/edit', name: 'app_competition_type_edit', methods: ['POST'])]
    public function edit(CompetitionType $type, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CompetitionTypeType::class, $type);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
        }
        return $this->redirectToRoute('app_competition_type_index');
    }
}
