<?php

namespace App\Controller;

use App\Entity\Competition;
use App\Form\CompetitionFormType;
use App\Repository\CompetitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/competitions')]
class CompetitionController extends AbstractController
{
    private string $documentsDir;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir)
    {
        $this->documentsDir = $projectDir . '/public/uploads/competitions';
    }

    #[Route('/{id}', name: 'app_competition_show', requirements: ['id' => '\d+'])]
    public function show(Competition $competition): Response
    {
        return $this->render('competition/show.html.twig', [
            'competition' => $competition,
            'canEdit'     => $this->isGranted('ROLE_COACH'),
        ]);
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/new', name: 'app_competition_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $competition = new Competition();

        if ($date = $request->query->get('date')) {
            try { $competition->setDate(new \DateTime($date)); } catch (\Exception) {}
        }

        $form = $this->createForm(CompetitionFormType::class, $competition);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleDocumentUpload($form, $competition, $slugger);
            $em->persist($competition);
            $em->flush();
            $this->addFlash('success', 'Compétition créée.');
            return $this->redirectToRoute('app_competition_calendar', [
                'month' => $competition->getDate()->format('n'),
                'year'  => $competition->getDate()->format('Y'),
            ]);
        }

        return $this->render('competition/new.html.twig', ['form' => $form]);
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/{id}/edit', name: 'app_competition_edit')]
    public function edit(Competition $competition, Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(CompetitionFormType::class, $competition);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleDocumentUpload($form, $competition, $slugger);
            $em->flush();
            $this->addFlash('success', 'Compétition mise à jour.');
            return $this->redirectToRoute('app_competition_show', ['id' => $competition->getId()]);
        }

        return $this->render('competition/edit.html.twig', ['form' => $form, 'competition' => $competition]);
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/{id}/reschedule', name: 'app_competition_reschedule', methods: ['POST'])]
    public function reschedule(Competition $competition, Request $request, EntityManagerInterface $em): Response
    {
        $data = json_decode($request->getContent(), true);
        try {
            $competition->setDate(new \DateTime($data['date'] ?? ''));
            $em->flush();
            return $this->json(['ok' => true]);
        } catch (\Exception) {
            return $this->json(['ok' => false], 400);
        }
    }

    #[Route('/{id}/document', name: 'app_competition_document')]
    public function downloadDocument(Competition $competition): Response
    {
        if (!$competition->getDocumentFilename()) {
            throw $this->createNotFoundException('Aucun document associé.');
        }

        $filePath = $this->documentsDir . '/' . $competition->getDocumentFilename();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file($filePath, $competition->getTitle() . '-circulaire.' . pathinfo($filePath, PATHINFO_EXTENSION));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/delete', name: 'app_competition_delete', methods: ['POST'])]
    public function delete(Competition $competition, Request $request, EntityManagerInterface $em): Response
    {
        $month = $competition->getDate()->format('n');
        $year  = $competition->getDate()->format('Y');

        if ($this->isCsrfTokenValid('delete-competition' . $competition->getId(), $request->getPayload()->get('_token'))) {
            if ($competition->getDocumentFilename()) {
                $file = $this->documentsDir . '/' . $competition->getDocumentFilename();
                if (file_exists($file)) { unlink($file); }
            }
            $em->remove($competition);
            $em->flush();
            $this->addFlash('success', 'Compétition supprimée.');
        }
        return $this->redirectToRoute('app_competition_calendar', ['month' => $month, 'year' => $year]);
    }

    private function handleDocumentUpload($form, Competition $competition, SluggerInterface $slugger): void
    {
        $file = $form->get('documentFile')->getData();
        if (!$file) { return; }

        if (!is_dir($this->documentsDir)) {
            mkdir($this->documentsDir, 0755, true);
        }

        // Remove old file if replacing
        if ($competition->getDocumentFilename()) {
            $old = $this->documentsDir . '/' . $competition->getDocumentFilename();
            if (file_exists($old)) { unlink($old); }
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename     = $slugger->slug($originalFilename);
        $newFilename      = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $file->move($this->documentsDir, $newFilename);
        $competition->setDocumentFilename($newFilename);
    }
}
