<?php

namespace App\Controller;

use App\Entity\TrainingType;
use App\Form\TrainingTypeType;
use App\Repository\TrainingTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/settings/training-types')]
class TrainingTypeController extends AbstractController
{
    #[Route('', name: 'app_training_type_index')]
    public function index(TrainingTypeRepository $repo, Request $request, EntityManagerInterface $em): Response
    {
        // Inline create form on the same page
        $newType = new TrainingType();
        $form = $this->createForm(TrainingTypeType::class, $newType);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($newType);
            $em->flush();
            $this->addFlash('success', 'Type "' . $newType->getName() . '" créé.');
            return $this->redirectToRoute('app_training_type_index');
        }

        return $this->render('training_type/index.html.twig', [
            'types' => $repo->findAllOrdered(),
            'form'  => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_training_type_delete', methods: ['POST'])]
    public function delete(TrainingType $type, Request $request, EntityManagerInterface $em): Response
    {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if ($this->isCsrfTokenValid('delete-tt' . $type->getId(), $request->getPayload()->get('_token'))) {
            foreach ($type->getSessions() as $session) {
                $session->setTrainingType(null);
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

        return $this->redirectToRoute('app_training_type_index');
    }

    #[Route('/{id}/edit', name: 'app_training_type_edit', methods: ['POST'])]
    public function edit(TrainingType $type, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TrainingTypeType::class, $type);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
        }
        return $this->redirectToRoute('app_training_type_index');
    }
}
