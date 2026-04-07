<?php

namespace App\Controller;

use App\Entity\Athlete;
use App\Entity\User;
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
            $this->handlePhotoUpload($form, $athlete, $slugger, $request);
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

    #[Route('/{id}/stats', name: 'app_athlete_stats')]
    public function stats(Athlete $athlete, PerformanceRepository $performanceRepo): Response
    {
        $byDisc = $performanceRepo->findByAthleteGroupedByDiscipline($athlete);

        if (empty($byDisc)) {
            return $this->redirectToRoute('app_athlete_show', ['id' => $athlete->getId()]);
        }

        $lowerIsBetter = fn(string $unit): bool => in_array($unit, ['s', 'min:s']);

        $formatDiff = function (float $abs, string $unit) use ($lowerIsBetter): string {
            if ($lowerIsBetter($unit)) {
                if ($abs >= 60) {
                    return sprintf('%d:%05.2f', (int)($abs / 60), fmod($abs, 60));
                }
                return number_format($abs, 2) . 's';
            }
            return number_format($abs, 2) . ' ' . $unit;
        };

        $statsData = [];

        foreach ($byDisc as $discipline => $perfs) {
            // perfs are ASC by recordedAt (from findByAthleteGroupedByDiscipline)
            $unit   = $perfs[0]->getUnit();
            $lower  = $lowerIsBetter($unit);
            $values = array_map(fn($p) => (float) $p->getValue(), $perfs);
            $count  = count($perfs);

            /** @var Performance $first */
            $first = $perfs[0];
            /** @var Performance $last */
            $last = $perfs[$count - 1];

            // PB
            $bestValue = $lower ? min($values) : max($values);
            $bestIdx   = array_search($bestValue, $values);
            $bestPerf  = $perfs[$bestIdx];

            // Total progression: first → PB (plus représentatif que first → last)
            $rawDiff  = $bestValue - (float)$first->getValue();
            $improved = $lower ? $rawDiff < 0 : $rawDiff > 0;
            $days     = $count > 1 ? $first->getRecordedAt()->diff($bestPerf->getRecordedAt())->days : 0;
            $months   = $days > 0 ? round($days / 30.5, 1) : 0;

            // SB saison passée → PB actuel
            $now              = new \DateTimeImmutable();
            $curSeasonStart   = (int)$now->format('n') >= 9 ? (int)$now->format('Y') : (int)$now->format('Y') - 1;
            $prevSeasonStart  = $curSeasonStart - 1;
            $prevSeasonPerfs  = array_filter($perfs, function ($p) use ($prevSeasonStart) {
                $y = (int)$p->getRecordedAt()->format('Y');
                $m = (int)$p->getRecordedAt()->format('n');
                return ($m >= 9 ? $y : $y - 1) === $prevSeasonStart;
            });
            $sbLastSeason = null;
            if ($prevSeasonPerfs) {
                $prevVals     = array_map(fn($p) => (float)$p->getValue(), $prevSeasonPerfs);
                $sbValue      = $lower ? min($prevVals) : max($prevVals);
                $sbRawDiff    = $bestValue - $sbValue;
                $sbImproved   = $lower ? $sbRawDiff < 0 : $sbRawDiff > 0;
                $sbLastSeason = [
                    'formatted' => $formatDiff(abs($sbRawDiff), $unit),
                    'improved'  => $sbImproved,
                    'sbFormatted' => $formatDiff($sbValue, $unit),
                    'season'    => $prevSeasonStart . '-' . ($prevSeasonStart + 1),
                ];
            }

            // Best single-session improvement
            $bestSingleGain = 0.0;
            for ($i = 1; $i < $count; $i++) {
                $delta = (float)$perfs[$i]->getValue() - (float)$perfs[$i - 1]->getValue();
                $gain  = $lower ? -$delta : $delta;
                if ($gain > $bestSingleGain) {
                    $bestSingleGain = $gain;
                }
            }

            // Consistency: standard deviation + CV
            $mean     = array_sum($values) / $count;
            $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / $count;
            $stdDev   = sqrt($variance);
            $cv       = $mean > 0 ? ($stdDev / $mean) * 100 : 0;
            // Score: CV~0%→100, CV~2%→70, CV~5%→25, CV>6.7%→0
            $consistencyScore = (int) max(0, min(100, 100 - $cv * 15));

            // Wind data — exclude indoor, and for wind=0 keep only the best
            $windPerfs = array_values(array_filter($perfs, fn($p) => $p->getWind() !== null && !$p->getIsIndoor()));
            $bestAtZero = null;
            $windFiltered = [];
            foreach ($windPerfs as $p) {
                if ((float) $p->getWind() === 0.0) {
                    if (!$bestAtZero || ($lower
                        ? (float) $p->getValue() < (float) $bestAtZero->getValue()
                        : (float) $p->getValue() > (float) $bestAtZero->getValue())) {
                        $bestAtZero = $p;
                    }
                } else {
                    $windFiltered[] = $p;
                }
            }
            if ($bestAtZero) {
                $windFiltered[] = $bestAtZero;
            }
            $windData = array_map(fn($p) => [
                'wind'      => (float) $p->getWind(),
                'value'     => (float) $p->getValue(),
                'formatted' => $p->getFormattedValue(),
                'date'      => $p->getRecordedAt()->format('d/m/Y'),
                'isComp'    => (bool) $p->getIsCompetition(),
            ], $windFiltered);

            // Best legal (wind ≤ 2.0)
            $legalPerfs = array_filter($windPerfs, fn($p) => abs((float)$p->getWind()) <= 2.0);
            $bestLegal  = null;
            if ($legalPerfs) {
                $bestLegal = array_reduce($legalPerfs, function ($carry, $p) use ($lower) {
                    if (!$carry) return $p;
                    return ($lower ? (float)$p->getValue() < (float)$carry->getValue()
                                   : (float)$p->getValue() > (float)$carry->getValue())
                        ? $p : $carry;
                });
            }

            // Distribution bins with sport-specific step sizes
            $bins = [];
            if ($count >= 3) {
                $minV = min($values);
                $maxV = max($values);
                if ($maxV > $minV) {
                    // Base step: 0.1s for time, 0.1m for distance, else smart rounding
                    $baseStep = match(true) {
                        in_array($unit, ['s', 'min:s']) => 0.1,
                        $unit === 'm'                   => 0.1,
                        $unit === 'kg'                  => 1.0,
                        $unit === 'pts'                 => 50.0,
                        default                         => ($maxV - $minV) / 8,
                    };
                    // Scale up step until we have ≤ 14 bins
                    $step = $baseStep;
                    while (($maxV - $minV) / $step > 14) {
                        $step *= 2;
                    }
                    $decimals = $step < 1 ? 2 : ($step < 10 ? 1 : 0);
                    $lo0 = floor($minV / $step) * $step;
                    $b   = 0;
                    while (true) {
                        $lo = round($lo0 + $b * $step, 6);
                        $hi = round($lo + $step, 6);
                        if ($lo > $maxV) break;
                        $n = count(array_filter($values, fn($v) => $v >= $lo && $v < $hi));
                        // include maxV in last bin
                        if ($hi > $maxV) {
                            $n = count(array_filter($values, fn($v) => $v >= $lo && $v <= $maxV));
                        }
                        $suffix = in_array($unit, ['s', 'min:s']) ? 's' : ($unit === 'm' ? 'm' : '');
                        $bins[] = [
                            'label' => number_format($lo, $decimals) . $suffix,
                            'count' => $n,
                        ];
                        $b++;
                    }
                    // Remove leading/trailing empty bins
                    while ($bins && $bins[0]['count'] === 0) array_shift($bins);
                    while ($bins && end($bins)['count'] === 0) array_pop($bins);
                }
            }

            $statsData[ucfirst($discipline)] = [
                'unit'         => $unit,
                'lower'        => $lower,
                'count'        => $count,
                'first'        => ['formatted' => $first->getFormattedValue(), 'date' => $first->getRecordedAt()->format('d/m/Y')],
                'last'         => ['formatted' => $last->getFormattedValue(),  'date' => $last->getRecordedAt()->format('d/m/Y')],
                'pb'           => ['formatted' => $bestPerf->getFormattedValue(), 'date' => $bestPerf->getRecordedAt()->format('d/m/Y')],
                'progression'  => [
                    'abs'         => abs($rawDiff),
                    'formatted'   => $count > 1 ? $formatDiff(abs($rawDiff), $unit) : null,
                    'improved'    => $count > 1 ? $improved : null,
                    'months'      => $months,
                    'sbLastSeason'=> $sbLastSeason,
                ],
                'bestGain'     => $bestSingleGain > 0 ? $formatDiff($bestSingleGain, $unit) : null,
                'consistency'  => [
                    'score'  => $consistencyScore,
                    'stdDev' => round($stdDev, 3),
                    'cv'     => round($cv, 2),
                ],
                'wind'         => $windData,
                'bestLegal'    => $bestLegal ? ['formatted' => $bestLegal->getFormattedValue(), 'wind' => (float)$bestLegal->getWind(), 'date' => $bestLegal->getRecordedAt()->format('d/m/Y')] : null,
                'bins'         => $bins,
                'chart'        => [
                    'labels' => array_map(fn($p) => $p->getRecordedAt()->format('Y-m-d'), $perfs),
                    'values' => array_map(fn($p) => (float) $p->getValue(), $perfs),
                    'isComp' => array_map(fn($p) => (bool) $p->getIsCompetition(), $perfs),
                ],
                'rawPerfs'     => array_map(fn($p) => [
                    'value'    => (float) $p->getValue(),
                    'date'     => $p->getRecordedAt()->format('d/m/Y'),
                    'dateRaw'  => $p->getRecordedAt()->format('Y-m-d'),
                    'year'     => (int) $p->getRecordedAt()->format('Y'),
                    'isComp'   => (bool) $p->getIsCompetition(),
                    'isIndoor' => (bool) $p->getIsIndoor(),
                    'wind'     => $p->getWind() !== null ? (float) $p->getWind() : null,
                ], $perfs),
            ];
        }

        return $this->render('athlete/stats.html.twig', [
            'athlete'     => $athlete,
            'statsData'   => $statsData,
            'disciplines' => array_keys($statsData),
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
            $this->handlePhotoUpload($form, $athlete, $slugger, $request);
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
            // Nullify any user linked to this athlete before deleting
            // (SQLite doesn't enforce onDelete="SET NULL" without PRAGMA foreign_keys=ON)
            $linkedUsers = $em->getRepository(User::class)->findBy(['linkedAthlete' => $athlete]);
            foreach ($linkedUsers as $u) {
                $u->setLinkedAthlete(null);
            }
            $em->remove($athlete);
            $em->flush();
            $this->addFlash('success', 'Athlète supprimé.');
        }
        return $this->redirectToRoute('app_athlete_index');
    }

    private function handlePhotoUpload($form, Athlete $athlete, SluggerInterface $slugger, Request $request = null): void
    {
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/athletes';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Priorité : image recadrée envoyée en base64
        $croppedData = $request?->request->get('cropped_photo_data');
        if ($croppedData && preg_match('/^data:image\/(jpeg|png|webp);base64,(.+)$/s', $croppedData, $m)) {
            $imageData = base64_decode($m[2]);
            if ($imageData !== false) {
                $newFilename = uniqid('photo-', true) . '.jpg';
                file_put_contents($uploadDir . '/' . $newFilename, $imageData);
                $athlete->setPhoto($newFilename);
                return;
            }
        }

        // Fallback : upload classique sans crop
        $photoFile = $form->get('photoFile')->getData();
        if ($photoFile) {
            $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

            try {
                $photoFile->move($uploadDir, $newFilename);
                $athlete->setPhoto($newFilename);
            } catch (FileException) {
                // ignore upload error
            }
        }
    }
}
