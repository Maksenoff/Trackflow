<?php

namespace App\Controller;

use App\Entity\Athlete;
use App\Form\AthleteType;
use App\Repository\AthleteRepository;
use App\Repository\AthleteSessionRepository;
use App\Repository\PerformanceRepository;
use App\Repository\SessionRepository;
use App\Entity\Performance;
use App\Service\FfaSync;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/athletes')]
class AthleteController extends AbstractController
{
    #[Route('', name: 'app_athlete_index')]
    public function index(AthleteRepository $repo): Response
    {
        return $this->render('athlete/index.html.twig', [
            'athletes' => $repo->findAllOrderedByName(),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/lookup-ffa', name: 'app_athlete_lookup_ffa', methods: ['POST'])]
    public function lookupFfa(Request $request, FfaSync $ffaSync): JsonResponse
    {
        $url = trim($request->request->get('url', ''));
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json(['error' => 'URL invalide.'], 400);
        }

        return $this->json($ffaSync->lookupProfile($url));
    }

    /** Debug only — dump raw HTML returned by athle.fr */
    #[Route('/debug-ffa', name: 'app_athlete_debug_ffa', methods: ['GET'])]
    public function debugFfa(Request $request, FfaSync $ffaSync): Response
    {
        $url = $request->query->get('url', '');
        if (!$url) return new Response('Pass ?url=...', 400);
        $html = $ffaSync->debugFetch($url);
        return new Response('<pre>' . htmlspecialchars(substr($html ?? 'NULL / no response', 0, 100000)) . '</pre>');
    }

    /** Debug only — show exactly what HTML athle.fr returns near birth date fields */
    #[Route('/debug-birthdate', name: 'app_athlete_debug_birthdate', methods: ['GET'])]
    public function debugBirthDate(Request $request, FfaSync $ffaSync): Response
    {
        $url = $request->query->get('url', '');
        if (!$url) return new Response('Pass ?url=...', 400);
        $info = $ffaSync->debugBirthDate($url);
        return new Response('<pre>' . htmlspecialchars(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>');
    }

    /** Debug only — show resolved bases URL + parsed results count */
    #[Route('/diagnose-ffa', name: 'app_athlete_diagnose_ffa', methods: ['GET'])]
    public function diagnoseFfa(Request $request, FfaSync $ffaSync): Response
    {
        $url = $request->query->get('url', '');
        if (!$url) return new Response('Pass ?url=...', 400);
        $info = $ffaSync->diagnose($url);
        return new Response('<pre>' . htmlspecialchars(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>');
    }

    #[IsGranted('ROLE_COACH')]
    #[Route('/new', name: 'app_athlete_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $athlete = new Athlete();
        $form = $this->createForm(AthleteType::class, $athlete);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $disciplinesRaw = $request->request->get('disciplines_raw', '[]');
            $athlete->setDisciplines(json_decode($disciplinesRaw, true) ?: []);
            $this->handlePhotoUpload($form, $athlete, $slugger);
            $em->persist($athlete);
            $em->flush();
            $this->addFlash('success', 'Athlète ajouté avec succès.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athlete->getId()]);
        }

        return $this->render('athlete/new.html.twig', [
            'form'        => $form,
            'athlete'     => $athlete,
            'disciplines' => Performance::DISCIPLINES,
        ]);
    }

    #[Route('/{id}', name: 'app_athlete_show')]
    public function show(
        Athlete $athlete,
        PerformanceRepository $performanceRepo,
        SessionRepository $sessionRepo,
        AthleteSessionRepository $asRepo,
        CsrfTokenManagerInterface $csrf
    ): Response {
        $allPerformances = $performanceRepo->findBy(['athlete' => $athlete], ['recordedAt' => 'DESC']);

        $birthYear = $athlete->getBirthDate() ? (int) $athlete->getBirthDate()->format('Y') : null;

        $performancesJson = array_map(function (Performance $p) use ($athlete, $csrf, $birthYear) {
            $date  = $p->getRecordedAt();
            $year  = (int) $date->format('Y');
            $month = (int) $date->format('n');
            // Season: Sept–Aug, named by start year (e.g. "2024-25" for Sept 2024 – Aug 2025)
            $seasonStart = $month >= 9 ? $year : $year - 1;
            $season      = $seasonStart . '-' . ($seasonStart + 1);
            $seasonShort = $seasonStart . '-' . substr((string)($seasonStart + 1), 2);
            // FFA category based on age at Dec 31 of competition year
            $category = null;
            if ($birthYear) {
                $age = $year - $birthYear;
                $category = match (true) {
                    $age <= 9  => 'Poussin',
                    $age <= 11 => 'Benjamin',
                    $age <= 13 => 'Minime',
                    $age <= 15 => 'Cadet',
                    $age <= 17 => 'Junior',
                    $age <= 22 => 'Espoir',
                    $age <= 34 => 'Senior',
                    default    => 'Master',
                };
            }

            return [
                'id'             => $p->getId(),
                'discipline'     => ucfirst($p->getDiscipline()),
                'unit'           => $p->getUnit(),
                'value'          => (float) $p->getValue(),
                'formattedValue' => $p->getFormattedValue(),
                'dateFormatted'  => $date->format('d/m/Y'),
                'dateRaw'        => $date->format('Y-m-d'),
                'season'         => $season,
                'seasonShort'    => $seasonShort,
                'seasonStart'    => $seasonStart,
                'category'       => $category,
                'isCompetition'  => (bool) $p->getIsCompetition(),
                'isPersonalBest' => (bool) $p->getIsPersonalBest(),
                'isIndoor'       => $p->getIsIndoor(),
                'venue'          => $p->getVenue() ?? '',
                'level'          => $p->getLevel() ?? '',
                'levelPoints'    => $p->getLevelPoints(),
                'notes'          => $p->getNotes() ?? '',
                'wind'           => $p->getWind(),
                'editUrl'        => $this->generateUrl('app_performance_edit', ['athleteId' => $athlete->getId(), 'id' => $p->getId()]),
                'deleteUrl'      => $this->generateUrl('app_performance_delete', ['athleteId' => $athlete->getId(), 'id' => $p->getId()]),
                'csrfToken'      => $csrf->getToken('delete-perf' . $p->getId())->getValue(),
            ];
        }, $allPerformances);

        // Chart data per discipline (chronological for chart, from oldest to newest)
        $performancesByDiscipline = $performanceRepo->findByAthleteGroupedByDiscipline($athlete);
        $chartData = [];
        foreach ($performancesByDiscipline as $discipline => $perfs) {
            $unit = $perfs[0]->getUnit();
            $chronological = array_reverse($perfs);
            $chartData[$discipline] = [
                'labels'         => array_map(fn($p) => $p->getRecordedAt()->format('d/m/Y'), $chronological),
                'values'         => array_map(fn($p) => (float) $p->getValue(), $chronological),
                'unit'           => $unit,
                'higherIsBetter' => $unit !== 's',
            ];
        }

        $pastSessions     = $sessionRepo->findPastSessions(30);
        $loggedIds        = $asRepo->findLoggedSessionIds($athlete);
        $unloggedSessions = array_filter($pastSessions, fn($s) => !in_array($s->getId(), $loggedIds));
        $upcomingSessions = $sessionRepo->findUpcomingSessions(10);

        $isOwnProfile = $this->isGranted('ROLE_COACH') || ($this->getUser()->getLinkedAthlete()?->getId() === $athlete->getId());
        $canEdit      = $isOwnProfile;

        return $this->render('athlete/show.html.twig', [
            'athlete'                  => $athlete,
            'performancesJson'         => $performancesJson,
            'performancesByDiscipline' => $performancesByDiscipline,
            'chartData'                => $chartData,
            'unloggedSessions'         => $canEdit ? array_values($unloggedSessions) : [],
            'loggedSessions'           => $canEdit ? $asRepo->findByAthlete($athlete) : [],
            'upcomingSessions'         => $upcomingSessions,
            'isOwnProfile'             => $isOwnProfile,
            'canEdit'                  => $canEdit,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_athlete_edit')]
    public function edit(Request $request, Athlete $athlete, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        if (!$this->isGranted('ROLE_COACH') && $this->getUser()->getLinkedAthlete()?->getId() !== $athlete->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AthleteType::class, $athlete);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $disciplinesRaw = $request->request->get('disciplines_raw', '[]');
            $athlete->setDisciplines(json_decode($disciplinesRaw, true) ?: []);
            $this->handlePhotoUpload($form, $athlete, $slugger);
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('app_athlete_show', ['id' => $athlete->getId()]);
        }

        return $this->render('athlete/edit.html.twig', [
            'form'        => $form,
            'athlete'     => $athlete,
            'disciplines' => Performance::DISCIPLINES,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/sync-ffa', name: 'app_athlete_sync_ffa', methods: ['POST'])]
    public function syncFfa(Request $request, Athlete $athlete, FfaSync $ffaSync): JsonResponse
    {
        // Cache: skip if synced less than 5 min ago AND not a forced manual sync
        $force    = (bool) $request->request->get('force', false);
        $lastSync = $athlete->getLastSyncedAt();
        if (!$force && $lastSync && $lastSync > new \DateTimeImmutable('-5 minutes')) {
            return $this->json([
                'cached'   => true,
                'imported' => 0,
                'skipped'  => 0,
                'error'    => null,
                'lastSync' => $lastSync->format('H:i'),
            ]);
        }

        $result = $ffaSync->sync($athlete);
        $result['cached']   = false;
        $result['lastSync'] = (new \DateTimeImmutable())->format('H:i');

        return $this->json($result);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/delete', name: 'app_athlete_delete', methods: ['POST'])]
    public function delete(Request $request, Athlete $athlete, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $athlete->getId(), $request->getPayload()->get('_token'))) {
            $em->remove($athlete);
            $em->flush();
            $this->addFlash('success', 'Athlète supprimé.');
        }
        return $this->redirectToRoute('app_athlete_index');
    }

    private function handlePhotoUpload($form, Athlete $athlete, SluggerInterface $slugger): void
    {
        $photoFile = $form->get('photoFile')->getData();
        if ($photoFile) {
            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/athletes';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            try {
                $photoFile->move($uploadDir, $newFilename);
                $athlete->setPhoto($newFilename);
            } catch (FileException) {
                // ignore upload error
            }
        }
    }
}
