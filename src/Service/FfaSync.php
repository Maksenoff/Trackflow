<?php

namespace App\Service;

use App\Entity\Athlete;
use App\Entity\Performance;
use App\Repository\PerformanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FfaSync
{
    private const ATHLETE_PAGE_URL = 'https://www.athle.fr/athletes/%s/resultats';
    private const AJAX_URL         = 'https://www.athle.fr/ajax/fiche-athlete-resultats.aspx';

    /** [regex, discipline, unit] — checked against both raw and space-compacted discipline string */
    private const DISCIPLINE_PATTERNS = [
        // ── Haies (avant les courses plates pour éviter les faux matchs) ──────
        // Haies jeunes
        ['/^50\s*m\s*(haies?|h\.?)\b/i',              '50m-haies',    's'],
        ['/^80\s*m\s*(haies?|h\.?)\b/i',              '80m-haies',    's'],
        // Haies adultes
        ['/^60\s*m\s*(haies?|h\.?)\b/i',              '60m-haies',    's'],
        ['/^100\s*m\s*(haies?|h\.?)\b/i',             '100m-haies',   's'],  // femmes
        ['/^110\s*m\s*(haies?|h\.?)\b/i',             '110m-haies',   's'],  // hommes
        ['/^400\s*m\s*(haies?|h\.?)\b/i',             '400m-haies',   's'],
        // ── Courses plates — distances adultes ────────────────────────────────
        ['/^60\s*m\b/i',                              '60m',          's'],
        ['/^100\s*m\b/i',                             '100m',         's'],
        ['/^200\s*m\b/i',                             '200m',         's'],
        ['/^400\s*m\b/i',                             '400m',         's'],
        ['/^800\s*m\b/i',                             '800m',         's'],
        ['/^1\s*500\s*m\b/i',                         '1500m',        's'],
        ['/\bmile\b/i',                               '1500m',        's'],
        ['/^3\s*000\s*m\s*(st[eé]pple|steeplechase)\b/i', '3000m',   's'],  // steeple → 3000m
        ['/^3\s*000\s*m\b/i',                         '3000m',        's'],
        ['/^5\s*000\s*m\b/i',                         '5000m',        's'],
        ['/^10\s*000\s*m\b/i',                        '10000m',       's'],
        ['/^10\s*km\b/i',                             '10000m',       's'],
        ['/semi.?marathon/i',                          'semi-marathon','s'],
        ['/^marathon\b/i',                             'marathon',     's'],
        // ── Courses plates — distances jeunes (NE PAS fusionner avec adultes) ─
        ['/^50\s*m\b/i',                              '50m',          's'],
        ['/^80\s*m\b/i',                              '80m',          's'],
        ['/^150\s*m\b/i',                             '150m',         's'],
        ['/^300\s*m\b/i',                             '300m',         's'],
        ['/^600\s*m\b/i',                             '600m',         's'],
        ['/^1\s*000\s*m\b/i',                         '1000m',        's'],
        ['/^2\s*000\s*m\b/i',                         '2000m',        's'],
        // ── Marche (avant "marteau") ───────────────────────────────────────────
        ['/march[e]?\b/iu',                            'marche',       's'],
        // ── Cross ─────────────────────────────────────────────────────────────
        ['/^cross\b/i',                               'cross',        's'],
        ['/course\s+des\s+as/i',                      'cross',        's'],
        ['/cross\s+des\s+as/i',                       'cross',        's'],
        ['/\btc[mfx]\b/i',                            'cross',        's'],
        // ── Sauts ─────────────────────────────────────────────────────────────
        ['/longueur/i',                               'longueur',     'm'],
        ['/hauteur/i',                                'hauteur',      'm'],
        ['/triple/i',                                 'triple',       'm'],
        ['/perche/i',                                 'perche',       'm'],
        // ── Lancers ───────────────────────────────────────────────────────────
        ['/poids/i',                                  'poids',        'm'],
        ['/disque/i',                                  'disque',       'm'],
        ['/javelot/i',                                'javelot',      'm'],
        ['/marteau/i',                                'marteau',      'm'],
        // ── Épreuves combinées (chacune dans son propre bucket) ───────────────
        ['/d[ée]cathlon/i',                           'decathlon',    'pts'],
        ['/heptathlon/i',                             'heptathlon',   'pts'],
        ['/pentathlon/i',                             'pentathlon',   'pts'],
        ['/triathlon/i',                              'triathlon',    'pts'],
        // ── Relais ────────────────────────────────────────────────────────────
        ['/^4\s*[xX×]\s*60/i',                        '4x100m',       's'],
        ['/^4\s*[xX×]\s*1\s*00/i',                   '4x100m',       's'],
        ['/^4\s*[xX×]\s*2\s*00/i',                   '4x200m',       's'],
        ['/^4\s*[xX×]\s*4\s*00/i',                   '4x400m',       's'],
        ['/^relais\b/i',                              'autre',        's'],
        ['/^ekiden\b/i',                              'autre',        's'],
        // ── Trail / route hors distances standards ────────────────────────────
        ['/^trail\b/i',                               'autre',        's'],
        ['/\d+\s*km\b/i',                             'autre',        's'],
        ['/\d+\s*miles?\b/i',                         'autre',        's'],
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private PerformanceRepository $performanceRepo,
    ) {}

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Fetch profile info from a new athle.fr athlete URL.
     * Returns: firstName, lastName, birthDate, gender, discipline, error
     */
    public function lookupProfile(string $url): array
    {
        $athleteId = $this->extractAthleteId($url);
        if ($athleteId === null) {
            return [
                'firstName' => null, 'lastName' => null, 'birthDate' => null,
                'gender' => null, 'discipline' => null,
                'error' => 'URL invalide. Utilisez le format : https://www.athle.fr/athletes/XXXXX/resultats',
            ];
        }

        [$html] = $this->fetchWithCookie(sprintf(self::ATHLETE_PAGE_URL, $athleteId));
        if ($html === null) {
            return [
                'firstName' => null, 'lastName' => null, 'birthDate' => null,
                'gender' => null, 'discipline' => null,
                'error' => 'Impossible de charger la page athlète. Vérifiez l\'URL.',
            ];
        }

        return $this->parseProfile($html);
    }

    /**
     * Import all competition results for an athlete.
     * The ffaProfileUrl must be a new athle.fr URL: https://www.athle.fr/athletes/{id}/...
     */
    public function sync(Athlete $athlete): array
    {
        $url = $athlete->getFfaProfileUrl();
        if (!$url) {
            return ['imported' => 0, 'skipped' => 0, 'error' => 'Aucune URL de profil renseignée.'];
        }

        $athleteId = $this->extractAthleteId($url);
        if ($athleteId === null) {
            return ['imported' => 0, 'skipped' => 0,
                    'error' => 'URL invalide. Format attendu : https://www.athle.fr/athletes/XXXXX/resultats'];
        }

        // Load the athlete page to get a session cookie + available years
        [$html, $cookie] = $this->fetchWithCookie(sprintf(self::ATHLETE_PAGE_URL, $athleteId));
        if ($html === null) {
            return ['imported' => 0, 'skipped' => 0, 'error' => 'Impossible de charger la page athlète.'];
        }

        $years = $this->extractAvailableYears($html);
        if (empty($years)) {
            $currentYear = (int) date('Y');
            $years = range($currentYear, max(2010, $currentYear - 5));
        }

        // Refresh athlete profile fields (gender, licenseNumber) — never touch name/birthDate/disciplines
        // Les disciplines sont gérées manuellement par l'utilisateur et ne doivent pas être écrasées par la FFA
        $profile = $this->parseProfile($html);
        $profileChanged = false;
        if (!empty($profile['gender']) && $athlete->getGender() !== $profile['gender']) {
            $athlete->setGender($profile['gender']);
            $profileChanged = true;
        }
        if (!empty($profile['licenseNumber']) && $athlete->getLicenseNumber() !== $profile['licenseNumber']) {
            $athlete->setLicenseNumber($profile['licenseNumber']);
            $profileChanged = true;
        }
        // Disciplines FFA uniquement si l'athlète n'en a pas encore défini manuellement
        if (empty($athlete->getDisciplines()) && !empty($profile['discipline'])) {
            $athlete->setDisciplines($profile['discipline']);
            $profileChanged = true;
        }
        if ($profileChanged) {
            $this->em->flush();
        }

        $imported = 0;
        $skipped  = 0;
        $updated  = 0;

        foreach ($years as $year) {
            $ajaxHtml = $this->fetchAjaxResults($athleteId, (int) $year, $cookie);
            if ($ajaxHtml === null) continue;

            $rows = $this->parseResults($ajaxHtml, (int) $year);
            foreach ($rows as [$discipline, $unit, $value, $date, $isIndoor, $venue, $level, $levelPts, $wind]) {
                $existing = $this->performanceRepo->findOneBy([
                    'athlete'    => $athlete,
                    'discipline' => $discipline,
                    'value'      => (string) $value,
                    'recordedAt' => $date,
                ]);

                if ($existing) {
                    // Always refresh all fields that may have been missing on first import
                    $changed = false;
                    if ($level !== null && $existing->getLevel() !== $level) {
                        $existing->setLevel($level);
                        $changed = true;
                    }
                    if ($levelPts !== null && $existing->getLevelPoints() !== $levelPts) {
                        $existing->setLevelPoints($levelPts);
                        $changed = true;
                    }
                    if ($wind !== null && $existing->getWind() !== $wind) {
                        $existing->setWind($wind);
                        $changed = true;
                    }
                    if ($venue !== null && $existing->getVenue() !== $venue) {
                        $existing->setVenue($venue);
                        $changed = true;
                    }
                    if ($existing->getIsIndoor() !== $isIndoor) {
                        $existing->setIsIndoor($isIndoor);
                        $changed = true;
                    }
                    if ($changed) $updated++;
                    $skipped++;
                    continue;
                }

                $perf = (new Performance())
                    ->setAthlete($athlete)
                    ->setDiscipline($discipline)
                    ->setUnit($unit)
                    ->setValue((string) $value)
                    ->setRecordedAt($date)
                    ->setIsCompetition(true)
                    ->setIsPersonalBest(false)
                    ->setIsIndoor($isIndoor)
                    ->setVenue($venue)
                    ->setLevel($level)
                    ->setLevelPoints($levelPts)
                    ->setWind($wind);

                $this->em->persist($perf);
                $imported++;
            }
        }

        if ($imported > 0 || $updated > 0) {
            $this->em->flush();
            if ($imported > 0) $this->updatePersonalBests($athlete);
        }

        $athlete->setLastSyncedAt(new \DateTimeImmutable());
        $this->em->flush();

        return ['imported' => $imported, 'skipped' => $skipped, 'error' => null];
    }

    /**
     * Recompute and mark personal bests (best value per discipline).
     */
    public function updatePersonalBests(Athlete $athlete): void
    {
        $all = $this->performanceRepo->findBy(['athlete' => $athlete]);
        if (!$all) return;

        foreach ($all as $p) $p->setIsPersonalBest(false);

        $bests = [];
        foreach ($all as $p) {
            $disc         = $p->getDiscipline();
            $val          = (float) $p->getValue();
            $higherBetter = !in_array($p->getUnit(), ['s']);

            if (!isset($bests[$disc])) {
                $bests[$disc] = $p;
            } else {
                $bestVal = (float) $bests[$disc]->getValue();
                if ($higherBetter ? $val > $bestVal : $val < $bestVal) {
                    $bests[$disc] = $p;
                }
            }
        }

        foreach ($bests as $p) $p->setIsPersonalBest(true);
        $this->em->flush();
    }

    /** Expose raw fetch for debug endpoint */
    public function debugFetch(string $url): ?string
    {
        [$html] = $this->fetchWithCookie($url);
        return $html;
    }

    /**
     * Debug birth date: return all HTML lines / text nodes that contain birth-related keywords,
     * plus the parsed result, so we can see exactly what athle.fr provides.
     */
    public function debugBirthDate(string $url): array
    {
        $athleteId = $this->extractAthleteId($url);
        if (!$athleteId) {
            return ['error' => 'URL invalide'];
        }

        [$html] = $this->fetchWithCookie(sprintf(self::ATHLETE_PAGE_URL, $athleteId));
        if (!$html) {
            return ['error' => 'Page non chargée'];
        }

        // Collect every line that contains a birth/date/naissance/nee/né keyword
        $keywords = '/n[eé]e?|naiss|birth|ddn|date.*nais|born|\d{2}[\/-]\d{2}[\/-]\d{4}|janvier|f[eé]v|mars|avril|mai|juin|juill|ao[uû]|sept|octobre|novembre|d[eé]c/iu';
        $lines = [];
        foreach (explode("\n", $html) as $i => $line) {
            if (preg_match($keywords, $line)) {
                $lines[] = ['line' => $i + 1, 'html' => trim($line)];
            }
        }

        // Also run actual parseProfile and return what it found
        $parsed = $this->parseProfile($html);

        return [
            'parsed'         => $parsed,
            'matchingLines'  => array_slice($lines, 0, 40),
        ];
    }

    /** Diagnose: show athlete ID, available years, and parsed result count for first year */
    public function diagnose(string $url): array
    {
        $athleteId = $this->extractAthleteId($url);
        if (!$athleteId) {
            return ['inputUrl' => $url, 'athleteId' => null,
                    'error' => 'Cannot extract athlete ID. Use https://www.athle.fr/athletes/XXXXX/resultats'];
        }

        $pageUrl = sprintf(self::ATHLETE_PAGE_URL, $athleteId);
        [$html, $cookie] = $this->fetchWithCookie($pageUrl);
        $years = $html ? $this->extractAvailableYears($html) : [];

        $testYear = !empty($years) ? (int) $years[0] : (int) date('Y');
        $ajaxHtml = $html ? $this->fetchAjaxResults($athleteId, $testYear, $cookie) : null;
        $rows     = $ajaxHtml ? $this->parseResults($ajaxHtml, $testYear) : [];

        // Collect all raw discipline strings across all years (matched + unmatched)
        $rawDisciplines = ['matched' => [], 'unmatched' => []];
        if ($html) {
            foreach ($years as $yr) {
                $yHtml = $this->fetchAjaxResults($athleteId, (int) $yr, $cookie);
                if (!$yHtml) continue;
                $crawler2 = new Crawler('<table>' . $yHtml . '</table>');
                try {
                    $crawler2->filter('tr.clickable')->each(function (Crawler $tr) use (&$rawDisciplines) {
                        $cells = $tr->filter('td');
                        if ($cells->count() < 2) return;
                        $texts = [];
                        $cells->each(function (Crawler $td, int $i) use (&$texts) { $texts[$i] = trim($td->text()); });
                        $raw = $texts[1] ?? '';
                        if (!$raw) return;
                        $mapped = $this->mapDiscipline($raw);
                        if ($mapped) {
                            $rawDisciplines['matched'][$raw] = $mapped[0];
                        } else {
                            $rawDisciplines['unmatched'][$raw] = true;
                        }
                    });
                } catch (\Throwable) {}
            }
            ksort($rawDisciplines['matched']);
            ksort($rawDisciplines['unmatched']);
            $rawDisciplines['unmatched'] = array_keys($rawDisciplines['unmatched']);
        }

        return [
            'inputUrl'     => $url,
            'athleteId'    => $athleteId,
            'pageUrl'      => $pageUrl,
            'htmlLength'   => $html ? strlen($html) : 0,
            'years'        => $years,
            'testYear'     => $testYear,
            'ajaxLength'   => $ajaxHtml ? strlen($ajaxHtml) : 0,
            'resultsFound' => count($rows),
            'firstResults' => array_slice(array_map(fn($r) => [
                'discipline' => $r[0],
                'unit'       => $r[1],
                'value'      => $r[2],
                'date'       => $r[3]->format('Y-m-d'),
            ], $rows), 0, 5),
            'disciplines'  => $rawDisciplines,
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Extract the numeric internal athlete ID from a new athle.fr URL.
     * Accepts:
     *   https://www.athle.fr/athletes/12345/resultats
     *   https://www.athle.fr/athletes/12345
     *   12345  (plain number)
     */
    private function extractAthleteId(string $url): ?string
    {
        $url = trim($url);

        // Plain number
        if (ctype_digit($url)) {
            return $url;
        }

        // /athletes/{id}
        if (preg_match('#/athletes/(\d+)#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Fetch a URL and return [html, cookieString].
     * Follows redirects and captures session cookies.
     */
    private function fetchWithCookie(string $url): array
    {
        try {
            $r = $this->httpClient->request('GET', $url, [
                'timeout'      => 20,
                'max_redirects' => 5,
                'headers' => [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9',
                ],
            ]);

            if ($r->getStatusCode() !== 200) return [null, null];

            // Collect all Set-Cookie values for session reuse
            $cookies = [];
            foreach ($r->getHeaders(false)['set-cookie'] ?? [] as $raw) {
                $part = explode(';', $raw)[0];
                $cookies[] = trim($part);
            }

            return [$r->getContent(false), implode('; ', $cookies)];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    /**
     * Call the AJAX results endpoint for a given athlete + year.
     * Returns the HTML fragment or null if empty/failed.
     */
    private function fetchAjaxResults(string $athleteId, int $year, ?string $cookie): ?string
    {
        $url = self::AJAX_URL . '?' . http_build_query(['seq' => $athleteId, 'annee' => $year]);
        try {
            $headers = [
                'User-Agent'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'           => 'text/html, */*; q=0.01',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer'          => sprintf(self::ATHLETE_PAGE_URL, $athleteId),
            ];
            if ($cookie) {
                $headers['Cookie'] = $cookie;
            }

            $r = $this->httpClient->request('GET', $url, [
                'timeout' => 20,
                'headers' => $headers,
            ]);

            if ($r->getStatusCode() !== 200) return null;
            $body = $r->getContent(false);
            return strlen($body) > 10 ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extract available years from the athlete page.
     * They appear as: data-value="2024" on year selector options.
     */
    private function extractAvailableYears(string $html): array
    {
        $years = [];
        if (preg_match_all('/class="[^"]*select-option-anneeAth[^"]*"[^>]*data-value="(\d{4})"/i', $html, $m)) {
            foreach ($m[1] as $y) {
                $int = (int) $y;
                if ($int >= 2000 && $int <= 2100) $years[] = $int;
            }
        }
        // Fallback: any data-value that looks like a year in the options area
        if (empty($years) && preg_match_all('/data-value="(\d{4})"/i', $html, $m)) {
            foreach ($m[1] as $y) {
                $int = (int) $y;
                if ($int >= 2000 && $int <= 2100) $years[] = $int;
            }
        }
        $years = array_values(array_unique($years));
        rsort($years);
        return $years;
    }

    /**
     * Parse athlete profile from the new athle.fr page HTML.
     * Extracts: firstName, lastName, birthDate, gender, discipline.
     */
    private function parseProfile(string $html): array
    {
        $crawler   = new Crawler($html);
        $firstName = null;
        $lastName  = null;

        // Name from <title>: "Firstname LASTNAME | Profile | FFA"
        try {
            $title = trim($crawler->filter('title')->first()->text());
            $part  = trim(explode('|', $title)[0]);
            if (mb_strlen($part) > 2 && stripos($part, 'fédération') === false) {
                [$firstName, $lastName] = $this->splitName($part);
            }
        } catch (\Throwable) {}

        // Fallback: h1 on the page
        if (!$firstName && !$lastName) {
            try {
                $node = $crawler->filter('h1')->first();
                if ($node->count()) {
                    [$firstName, $lastName] = $this->splitName(
                        trim(preg_replace('/\s+/', ' ', $node->text()))
                    );
                }
            } catch (\Throwable) {}
        }

        // Birth date / gender
        $birthDate = null;
        $birthYear = null;
        $gender    = null;

        // 1. <time datetime="YYYY-MM-DD"> — semantic HTML used by some modern pages
        try {
            $crawler->filter('time[datetime]')->each(function (Crawler $node) use (&$birthDate) {
                if ($birthDate !== null) return;
                $dt = $node->attr('datetime');
                if ($dt && preg_match('/^((?:19|20)\d{2})-(\d{2})-(\d{2})$/', $dt)) {
                    $birthDate = $dt;
                }
            });
        } catch (\Throwable) {}

        // 2. JSON-LD structured data: {"birthDate": "YYYY-MM-DD"} or {"birthDate": "YYYY"}
        if ($birthDate === null) {
            if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $ldMatches)) {
                foreach ($ldMatches[1] as $json) {
                    try {
                        $ld = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
                        $bd = $ld['birthDate'] ?? null;
                        if ($bd) {
                            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $bd)) {
                                $birthDate = $bd;
                            } elseif (preg_match('/^\d{4}$/', $bd)) {
                                $birthYear = $birthYear ?? ($bd . '-01-01');
                            }
                        }
                    } catch (\Throwable) {}
                }
            }
        }

        // 3. Text-node scan: "né(e) le dd/mm/yyyy", "naissance : dd/mm/yyyy", "Né(e) en : 1987"
        // HTML: <p>...<span>Né(e) en : </span>1987</p>
        // HTML: <p>...<span>Catégorie / Nationalité : </span>MA (M0)<span>/</span>M<span>/</span>FRA</p>
        try {
            $crawler->filter('p, span, div, td, li')->each(function (Crawler $node) use (&$birthDate, &$birthYear, &$gender) {
                $text = trim($node->text());
                // Full birth date: "né(e) le dd/mm/yyyy" or "Date de naissance : dd/mm/yyyy"
                if ($birthDate === null && preg_match('/(?:n[ée]e?\s*(?:le\s+)?|naissance\s*:\s*)(\d{2}\/\d{2}\/\d{4})/iu', $text, $m)) {
                    $dt = \DateTime::createFromFormat('d/m/Y', $m[1]);
                    if ($dt) $birthDate = $dt->format('Y-m-d');
                }
                // Standalone dd/mm/yyyy near birth keyword
                if ($birthDate === null && preg_match('/naiss|ddn|birth/iu', $text)
                    && preg_match('/\b(\d{2}\/\d{2}\/(?:19|20)\d{2})\b/', $text, $m2)) {
                    $dt = \DateTime::createFromFormat('d/m/Y', $m2[1]);
                    if ($dt) $birthDate = $dt->format('Y-m-d');
                }
                // Birth year fallback: "Né(e) en : 1987"
                if ($birthYear === null && preg_match('/N[ée]\(?e?\)?\s+en\s*:\s*(\d{4})/iu', $text, $m)) {
                    $birthYear = $m[1] . '-01-01';
                }
                // Gender from "Catégorie / Nationalité : MA (M0) / M / FRA"
                if ($gender === null && preg_match('/Cat[ée]gorie/iu', $text)) {
                    $parts = preg_split('/\//', $text);
                    foreach ($parts as $part) {
                        $trimmed = trim($part);
                        if ($trimmed === 'M' || $trimmed === 'F') { $gender = $trimmed; break; }
                    }
                }
            });
        } catch (\Throwable) {}
        $birthDate = $birthDate ?? $birthYear;

        // License number
        $licenseNumber = null;
        try {
            // 1. JSON-LD: "identifier" field
            if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $ldM)) {
                foreach ($ldM[1] as $json) {
                    try {
                        $ld = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
                        foreach (['identifier', 'membershipNumber', 'licenseNumber'] as $key) {
                            if (!empty($ld[$key]) && preg_match('/^\d{6,10}$/', trim((string)$ld[$key]))) {
                                $licenseNumber = trim((string)$ld[$key]);
                                break 2;
                            }
                        }
                    } catch (\Throwable) {}
                }
            }
            // 2. Text scan for "Licence : XXXXXXXX" or "N° : XXXXXXXX"
            if ($licenseNumber === null) {
                $crawler->filter('p, span, div, td, li, dt, dd')->each(function (Crawler $node) use (&$licenseNumber) {
                    if ($licenseNumber !== null) return;
                    $text = trim($node->text());
                    if (preg_match('/licen[cs]e?\s*[:\-n°#]?\s*(\d{6,10})\b/iu', $text, $m)) {
                        $licenseNumber = $m[1];
                    } elseif (preg_match('/\bn[°o]\s*(?:de\s+)?licen[cs]e?\s*[:\-]?\s*(\d{6,10})\b/iu', $text, $m)) {
                        $licenseNumber = $m[1];
                    }
                });
            }
            // 3. Fallback: meta tag
            if ($licenseNumber === null && preg_match('/content=["\'](\d{6,10})["\'][^>]*licen/i', $html, $m)) {
                $licenseNumber = $m[1];
            }
        } catch (\Throwable) {}

        // Dominant discipline from any td text on the page
        $discipline = null;
        $counts     = [];
        try {
            $crawler->filter('td')->each(function (Crawler $node) use (&$counts) {
                $mapped = $this->mapDiscipline(trim($node->text()));
                if ($mapped) {
                    $counts[$mapped[0]] = ($counts[$mapped[0]] ?? 0) + 1;
                }
            });
        } catch (\Throwable) {}
        if ($counts) { arsort($counts); $discipline = array_key_first($counts); }

        return [
            'firstName'     => $firstName,
            'lastName'      => $lastName,
            'birthDate'     => $birthDate,
            'gender'        => $gender,
            'discipline'    => $discipline ? [$discipline] : [],
            'licenseNumber' => $licenseNumber,
            'error'         => (!$firstName && !$lastName)
                ? 'Nom introuvable. Vérifiez l\'URL (format : https://www.athle.fr/athletes/XXXXX/resultats).'
                : null,
        ];
    }

    /**
     * Parse results from the AJAX HTML fragment returned by fiche-athlete-resultats.aspx.
     *
     * Column order: Date | Epreuve | Performance | Vent | Tour | Place | Niveau | Points | Lieu
     * Rows with class "clickable" are data rows; "detail-row" rows are duplicates — skip them.
     * Date is partial ("12 Mai") so the year is injected from the AJAX parameter.
     * Returns: [discipline, unit, value, date, isIndoor, venue]
     */
    private function parseResults(string $html, int $year): array
    {
        // Wrap fragment in a table so DomCrawler can parse it
        $crawler = new Crawler('<table>' . $html . '</table>');
        $results = [];

        try {
            $crawler->filter('tr.clickable')->each(function (Crawler $tr) use (&$results, $year) {
                $cells = $tr->filter('td');
                if ($cells->count() < 3) return;

                $texts = [];
                $cells->each(function (Crawler $td, int $i) use (&$texts) {
                    $texts[$i] = trim($td->text());
                });

                $rawDisc = $texts[1] ?? '';
                $mapped  = $this->mapDiscipline($rawDisc);
                if (!$mapped) return;

                [$discipline, $unit] = $mapped;
                $value    = $this->parsePerf($texts[2] ?? '', $unit);
                $date     = $this->parsePartialDate($texts[0] ?? '', $year);
                $isIndoor = (bool) preg_match('/\b(salle|indoor|piste\s+courte?)\b/iu', $rawDisc);
                $venue    = $texts[8] ?? null;
                if ($venue === '') $venue = null;
                $level    = $texts[6] ?? null;
                if ($level === '' || $level === '-') $level = null;
                $levelPtsRaw = trim($texts[7] ?? '');
                $levelPts = is_numeric($levelPtsRaw) ? (int) $levelPtsRaw : null;
                $windRaw  = trim($texts[3] ?? '');
                $wind     = $this->parseWind($windRaw);

                if ($value !== null && $date !== null) {
                    $results[] = [$discipline, $unit, $value, $date, $isIndoor, $venue, $level, $levelPts, $wind];
                }
            });
        } catch (\Throwable) {}

        // Deduplicate on discipline+value+date
        $seen   = [];
        $unique = [];
        foreach ($results as $r) {
            $key = $r[0] . '|' . $r[2] . '|' . $r[3]->format('Y-m-d');
            if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $r; }
        }


        return $unique;
    }

    /**
     * Parse a wind string from athle.fr.
     * Returns "+1.2", "-0.3", "NC", or null (empty / not applicable).
     */
    private function parseWind(string $raw): ?string
    {
        if ($raw === '' || $raw === '-') return null;
        $upper = strtoupper($raw);
        if ($upper === 'NC') return 'NC';
        // Normalize: replace comma with dot, keep sign
        $clean = str_replace(',', '.', $raw);
        if (preg_match('/^[+\-]?\d+(?:\.\d+)?$/', $clean)) {
            $val = (float) $clean;
            return ($val >= 0 ? '+' : '') . number_format($val, 1, '.', '');
        }
        return null;
    }

    /**
     * Map a discipline name to [discipline, unit].
     * Handles new athle.fr format ("3 000m", "100m haies") and old format ("100 M", "LONGUEUR").
     */
    private function mapDiscipline(string $raw): ?array
    {
        // Strip indoor/salle/tcm suffix
        $norm = preg_replace('/[-\s]*(salle|indoor|en\s+salle|tcm|piste\s+couverte)\s*$/iu', '', $raw);
        $norm = trim(preg_replace('/\s+/', ' ', $norm));
        // Compact version: remove spaces between digits ("3 000m" → "3000m")
        $compact = preg_replace('/(\d)\s+(\d)/u', '$1$2', $norm);

        foreach (self::DISCIPLINE_PATTERNS as [$pattern, $discipline, $unit]) {
            if (preg_match($pattern, $norm) || preg_match($pattern, $compact)) {
                return [$discipline, $unit];
            }
        }

        return null;
    }

    /**
     * Parse a performance string to a float (seconds, metres, or points).
     *
     * New athle.fr uses '' (two apostrophes) for hundredths separator.
     * Examples: "10'56''77" → 656.77s,  "39'14''" → 2354s,  "7''34" → 7.34s
     * Also handles old format with " (double-quote).
     * Parenthetical repetition is stripped: "38'58'' (38'58'')" → "38'58''"
     */
    private function parsePerf(string $raw, string $unit): ?float
    {
        // Strip trailing parenthetical
        $raw = trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $raw));
        if ($raw === '') return null;
        if (in_array(strtoupper($raw), ['-', 'DNS', 'DNF', 'DQ', 'NM', 'PM', 'AB', 'ABD', 'DISQ', 'NP'])) return null;

        // Normalise '' → " so the rest of the logic is uniform
        $raw = str_replace("''", '"', $raw);

        if ($unit === 's') {
            // h'mm"ss.hh  (e.g. 1h long races)
            if (preg_match("/^(\d+)[h'](\d+)['\"](\d+)[\".,](\d+)$/u", $raw, $m)) {
                return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3] + (int)$m[4] / 100;
            }
            // mm'ss"hh
            if (preg_match("/^(\d+)['](\d+)[\".,](\d+)$/u", $raw, $m)) {
                return (int)$m[1] * 60 + (int)$m[2] + (int)$m[3] / 100;
            }
            // mm'ss"  (no hundredths)
            if (preg_match("/^(\d+)['](\d+)[\"]?$/u", $raw, $m)) {
                return (int)$m[1] * 60 + (float)$m[2];
            }
            // ss"hh
            if (preg_match('/^(\d+)"(\d+)$/', $raw, $m)) {
                return (int)$m[1] + (int)$m[2] / 100;
            }
            // h:mm:ss.hh
            $parts = explode(':', str_replace(',', '.', $raw));
            if (count($parts) === 3) return (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (float)$parts[2];
            if (count($parts) === 2) return (int)$parts[0] * 60 + (float)$parts[1];
            $clean = str_replace(',', '.', $raw);
            return is_numeric($clean) ? (float)$clean : null;
        }

        // Field measurement "NNmCC" format: "53m02" = 53.02 m, "7m25" = 7.25 m, "1m89" = 1.89 m
        if (preg_match('/^(\d+)m(\d{1,2})$/i', $raw, $m)) {
            return (float)$m[1] + (int)str_pad($m[2], 2, '0') / 100;
        }

        // Combined event score "8 309 pts" → 8309
        $raw = preg_replace('/\s*pts\s*$/i', '', $raw);

        // Metres / points: strip spaces, replace comma with dot
        $clean = str_replace([' ', ','], ['', '.'], $raw);
        return is_numeric($clean) ? (float)$clean : null;
    }

    /**
     * Parse a partial date like "12 Mai" using the given year.
     * Also handles full dates ("12/05/2024", "12 Nov. 2023").
     */
    private function parsePartialDate(string $raw, int $year): ?\DateTime
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        // Full date formats
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt) { $dt->setTime(0, 0, 0); return $dt; }
        }

        static $months = [
            'jan' => 1, 'fév' => 2, 'fev' => 2, 'mar' => 3,
            'avr' => 4, 'mai' => 5, 'jui' => 6, 'jul' => 7,
            'aoû' => 8, 'aou' => 8, 'sep' => 9, 'oct' => 10,
            'nov' => 11, 'déc' => 12, 'dec' => 12,
        ];

        // Partial: "12 Mai" or "12 Déc."
        if (preg_match('/^(\d{1,2})\s+([A-Za-zÀ-ÿ]{2,5})\.?$/u', $raw, $m)) {
            $key = mb_strtolower(mb_substr($m[2], 0, 3), 'UTF-8');
            if (isset($months[$key])) {
                $dt = new \DateTime();
                $dt->setDate($year, $months[$key], (int)$m[1]);
                $dt->setTime(0, 0, 0);
                return $dt;
            }
        }

        // Full with explicit year: "12 Nov. 2023"
        if (preg_match('/(\d{1,2})\s+([A-Za-zÀ-ÿ]{2,5})\.?\s+(\d{4})/u', $raw, $m)) {
            $key = mb_strtolower(mb_substr($m[2], 0, 3), 'UTF-8');
            if (isset($months[$key])) {
                $dt = new \DateTime();
                $dt->setDate((int)$m[3], $months[$key], (int)$m[1]);
                $dt->setTime(0, 0, 0);
                return $dt;
            }
        }

        return null;
    }

    /**
     * Split "Firstname LASTNAME" or "LASTNAME Firstname" into [firstName, lastName].
     * ALL-CAPS words → last name, mixed-case → first name.
     */
    private function splitName(string $text): array
    {
        $text  = preg_replace('/\s*[\|\-].*$/u', '', $text);
        $text  = trim($text);
        $words = preg_split('/\s+/u', $text) ?: [];
        if (count($words) < 2) return [null, null];

        $upper = [];
        $mixed = [];
        foreach ($words as $w) {
            $clean = preg_replace('/[^[:alpha:]]/u', '', $w);
            if ($clean && mb_strtoupper($clean, 'UTF-8') === $clean) {
                $upper[] = $w;
            } else {
                $mixed[] = $w;
            }
        }

        if (empty($upper) || empty($mixed)) return [null, null];

        return [
            mb_convert_case(implode(' ', $mixed), MB_CASE_TITLE, 'UTF-8'),
            mb_strtoupper(implode(' ', $upper), 'UTF-8'),
        ];
    }
}
