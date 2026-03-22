<?php

namespace App\Controller;

use App\Entity\Athlete;
use App\Entity\AthleteVideo;
use App\Entity\Performance;
use App\Repository\AthleteVideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/athletes/{athleteId}/videos')]
class AthleteVideoController extends AbstractController
{
    public function __construct(private CsrfTokenManagerInterface $csrf) {}

    #[Route('', name: 'app_athlete_videos', methods: ['GET'])]
    public function index(int $athleteId, EntityManagerInterface $em, AthleteVideoRepository $repo): Response
    {
        $athlete = $em->getRepository(Athlete::class)->find($athleteId);
        if (!$athlete) throw $this->createNotFoundException();

        return $this->json([
            'videos' => array_map(
                fn(AthleteVideo $v) => $this->serialize($v),
                $repo->findBy(['athlete' => $athlete], ['createdAt' => 'DESC'])
            ),
        ]);
    }

    #[Route('/upload', name: 'app_athlete_video_upload', methods: ['POST'])]
    public function upload(
        int $athleteId,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): JsonResponse {
        $athlete = $em->getRepository(Athlete::class)->find($athleteId);
        if (!$athlete) throw $this->createNotFoundException();

        $file = $request->files->get('video');
        if (!$file) {
            return $this->json(['error' => 'Aucun fichier reçu (vérifie que le champ s\'appelle "video" et que PHP accepte les uploads).'], 400);
        }

        if (!$file->isValid()) {
            return $this->json(['error' => 'Erreur PHP upload (code ' . $file->getError() . '): ' . $file->getErrorMessage()], 400);
        }

        $allowedExts = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v', 'ogv', '3gp'];
        $clientExt   = strtolower($file->getClientOriginalExtension());
        if (!in_array($clientExt, $allowedExts)) {
            return $this->json(['error' => 'Extension "' . $clientExt . '" non supportée. Utilisez MP4, WebM, MOV ou AVI.'], 400);
        }

        $maxSize = 200 * 1024 * 1024; // 200 MB
        if ($file->getSize() > $maxSize) {
            return $this->json(['error' => 'Fichier trop lourd (' . round($file->getSize() / 1024 / 1024) . ' Mo, max 200 Mo).'], 400);
        }

        $title      = trim($request->request->get('title', 'Sans titre'));
        $discipline = $request->request->get('discipline') ?: null;

        $safe     = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $ext      = $clientExt ?: ($file->guessExtension() ?? 'mp4');
        $filename = $safe . '-' . uniqid() . '.' . $ext;

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/videos';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->move($uploadDir, $filename);

        $video = new AthleteVideo();
        $video->setAthlete($athlete)->setTitle($title)->setDiscipline($discipline)->setFilename($filename);
        $em->persist($video);
        $em->flush();

        return $this->json($this->serialize($video), 201);
    }

    #[Route('/{id}/delete', name: 'app_athlete_video_delete', methods: ['POST'])]
    public function delete(
        int $athleteId,
        AthleteVideo $video,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($video->getAthlete()->getId() !== $athleteId) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete-video' . $video->getId(), $request->request->get('_token'))) {
            return $this->json(['error' => 'Token invalide.'], 403);
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/uploads/videos/' . $video->getFilename();
        if (file_exists($path)) {
            unlink($path);
        }

        $em->remove($video);
        $em->flush();

        return $this->json(['success' => true]);
    }

    private function serialize(AthleteVideo $v): array
    {
        return [
            'id'         => $v->getId(),
            'title'      => $v->getTitle(),
            'discipline' => $v->getDiscipline(),
            'url'        => '/uploads/videos/' . $v->getFilename(),
            'createdAt'  => $v->getCreatedAt()->format('d/m/Y'),
            'csrfToken'  => $this->csrf->getToken('delete-video' . $v->getId())->getValue(),
        ];
    }
}
