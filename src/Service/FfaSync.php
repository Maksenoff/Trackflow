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
    private const BASES_URL = 'https://bases.athle.fr/asp.net/athletes.aspx';

    /** Ordered list of [regex, discipline, unit] for mapDiscipline(). */
    private const DISCIPLINE_PATTERNS = [
        ['/^60\s*M?\s*H/i',                    '60m-haies',    's'],
        ['/^110\s*M?\s*H/i',                   '110m-haies',   's'],
        ['/^100\s*M?\s*H/i',                   '110m-haies',   's'],
        ['/^400\s*M?\s*H/i',                   '400m-haies',   's'],
        ['/^60\s*M/i',                          '60m',          's'],
        ['/^100\s*M/i',                         '100m',         's'],
        ['/^200\s*M/i',                         '200m',         's'],
        ['/^400\s*M/i',                         '400m',         's'],
        ['/^800\s*M/i',                         '800m',         's'],
        ['/^1\s*000\s*M|^1000\s*M/i',          '800m',         's'],
        ['/^1\s*500\s*M|^1500\s*M/i',          '1500m',        's'],
        ['/^2\s*000\s*M|^2000\s*M/i',          '3000m',        's'],
        ['/^3\s*000\s*M|^3000\s*M/i',          '3000m',        's'],
        ['/^5\s*000\s*M|^5000\s*M/i',          '5000m',        's'],
        ['/^10\s*000\s*M|^10000\s*M/i',        '10000m',       's'],
        ['/^10\s*KM|^10KM/i',                  '10000m',       's'],
        ['/SEMI.MARATHON/i',                    'semi-marathon','s'],
        ['/^MARATHON/i',                        'marathon',     's'],
        ['/LONGUEUR/i',                         'longueur',     'm'],
        ['/HAUTEUR/i',                          'hauteur',      'm'],
        ['/TRIPLE/i',                           'triple',       'm'],
        ['/PERCHE/i',                           'perche',       'm'],
        ['/POIDS/i',                            'poids',        'm'],
        ['/DISQUE/i',                           'disque',       'm'],
        ['/JAVELOT/i',                          'javelot',      'm'],
        ['/MARTEAU/i',                          'marteau',      'm'],
        ['/D[ÉE]CATHLON/i',                    'decathlon',    'pts'],
        ['/HEPTATHLON/i',                       'heptathlon',   'pts'],
        ['/PENTATHLON/i',                       'heptathlon',   'pts'],
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
     * Fetch profile info (name, birthDate…) from any athle.fr URL.
     * Also tries to discover the bases.athle.fr results URL automatically.
     * Returns array with keys: firstName, lastName, birthDate, gender, discipline,
     *                          basesUrl (nullable), error (nullable)
     */
    public function lookupProfile(string $url): array
    {
        $html = $this->fetch($url);
        if ($html === null) {
            return ['firstName' => null, 'lastName' => null, 'birthDate' => null,
                    'gender' => null, 'discipline' => null, 'basesUrl' => null,
                    'error' => 'Impossible de charger la page. Vérifiez l\'URL.'];
        }

        $profile = $this->parseProfile($html);

        // Fast path: seq= already in the URL
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        if (!empty($params['seq'])) {
            $profile['basesUrl'] = self::BASES_URL . '?' . http_build_query(['base' => 'resultats', 'seq' => $params['seq']]);
        } else {
            $profile['basesUrl'] = $this->findBasesUrl($html, $url);
        }

        return $profile;
    }

    /**
     * Import all competition results from bases.athle.fr, iterating all years.
     * If the stored URL is an athle.fr profile page, it tries to find the bases URL first.
     */
    public function sync(Athlete $athlete): array
    {
        $url = $athlete->getFfaProfileUrl();
        if (!$url) {
            return ['imported' => 0, 'skipped' => 0, 'error' => 'Aucune URL de profil renseignée.'];
        }

        // Resolve bases.athle.fr URL (might already be one, or found by scraping)
        $basesUrl = $this->resolveBasesUrl($url);
        if ($basesUrl === null) {
            return ['imported' => 0, 'skipped' => 0,
                    'error' => 'Impossible de trouver la page de résultats sur bases.athle.fr.'];
        }

        // Extract seq (licence) from bases URL
        parse_str((string) parse_url($basesUrl, PHP_URL_QUERY), $params);
        $seq = $params['seq'] ?? null;
        if (!$seq) {
            return ['imported' => 0, 'skipped' => 0, 'error' => 'Paramètre seq introuvable dans l\'URL bases.athle.fr.'];
        }

        // Build URLs for every year from current back to 2010.
        // bases.athle.fr may use "saison" or "annee" as the year param — try both.
        $currentYear = (int) date('Y');
        $seasonUrls  = [$basesUrl]; // base URL (no year = latest / all)
        for ($y = $currentYear; $y >= 2010; $y--) {
            $seasonUrls[] = self::BASES_URL . '?' . http_build_query(['base' => 'resultats', 'seq' => $seq, 'saison' => $y]);
            $seasonUrls[] = self::BASES_URL . '?' . http_build_query(['base' => 'resultats', 'seq' => $seq, 'annee'  => $y]);
        }

        $imported = 0;
        $skipped  = 0;

        foreach ($seasonUrls as $seasonUrl) {
            $html = $this->fetch($seasonUrl);
            if ($html === null) continue;

            $rows = $this->parseResults($html);
            if (empty($rows)) continue;

            foreach ($rows as [$discipline, $unit, $value, $date]) {
                $existing = $this->performanceRepo->findOneBy([
                    'athlete'    => $athlete,
                    'discipline' => $discipline,
                    'value'      => (string) $value,
                    'recordedAt' => $date,
                ]);

                if ($existing) { $skipped++; continue; }

                $perf = (new Performance())
                    ->setAthlete($athlete)
                    ->setDiscipline($discipline)
                    ->setUnit($unit)
                    ->setValue((string) $value)
                    ->setRecordedAt($date)
                    ->setIsCompetition(true)
                    ->setIsPersonalBest(false);

                $this->em->persist($perf);
                $imported++;
            }
        }

        if ($imported > 0) {
            $this->em->flush();
            $this->updatePersonalBests($athlete);
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

        // Reset all
        foreach ($all as $p) $p->setIsPersonalBest(false);

        $bests = [];
        foreach ($all as $p) {
            $disc          = $p->getDiscipline();
            $val           = (float) $p->getValue();
            $higherBetter  = !in_array($p->getUnit(), ['s']);

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
        return $this->fetch($url);
    }

    /** Diagnose: show resolved bases URL, first-year URL, and parsed result count */
    public function diagnose(string $url): array
    {
        $basesUrl = $this->resolveBasesUrl($url);
        if (!$basesUrl) {
            return ['inputUrl' => $url, 'basesUrl' => null, 'error' => 'Could not resolve bases URL'];
        }

        parse_str((string) parse_url($basesUrl, PHP_URL_QUERY), $params);
        $seq = $params['seq'] ?? null;

        $currentYear = (int) date('Y');
        $testUrl  = self::BASES_URL . '?' . http_build_query(['base' => 'resultats', 'seq' => $seq, 'saison' => $currentYear]);
        $html     = $this->fetch($testUrl);
        $rows     = $html ? $this->parseResults($html) : [];

        return [
            'inputUrl'      => $url,
            'basesUrl'      => $basesUrl,
            'seq'           => $seq,
            'testUrl'       => $testUrl,
            'htmlLength'    => $html ? strlen($html) : 0,
            'resultsFound'  => count($rows),
            'firstResults'  => array_slice(array_map(fn($r) => [
                'discipline' => $r[0],
                'unit'       => $r[1],
                'value'      => $r[2],
                'date'       => $r[3]->format('Y-m-d'),
            ], $rows), 0, 5),
            'htmlSnippet'   => $html ? substr(strip_tags($html), 0, 2000) : null,
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * If $url is already on bases.athle.fr, return it directly.
     * If the URL has a seq= param (athle.fr profile), build the bases URL directly.
     * Otherwise fetch the page and scan for a bases.athle.fr link.
     */
    private function resolveBasesUrl(string $url): ?string
    {
        if (str_contains($url, 'bases.athle.fr')) {
            return $url;
        }

        // Fast path: seq= is already in the URL query string
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        if (!empty($params['seq'])) {
            return self::BASES_URL . '?' . http_build_query(['base' => 'resultats', 'seq' => $params['seq']]);
        }

        $html = $this->fetch($url);
        if ($html === null) return null;

        return $this->findBasesUrl($html, $url);
    }

    /**
     * Look for a bases.athle.fr results link anywhere in the HTML.
     * Also scans script tags for seq= references.
     */
    private function findBasesUrl(string $html, string $sourceUrl): ?string
    {
        // 1. Direct <a href> to bases.athle.fr
        $crawler = new Crawler($html);
        $found   = null;
        try {
            $crawler->filter('a')->each(function (Crawler $a) use (&$found) {
                if ($found) return;
                $href = $a->attr('href') ?? '';
                if (str_contains($href, 'bases.athle.fr') && str_contains($href, 'resultats')) {
                    $found = $href;
                }
            });
        } catch (\Throwable) {}
        if ($found) return $found;

        // 2. Any href containing bases.athle.fr (even without 'resultats')
        try {
            $crawler->filter('a[href*="bases.athle.fr"]')->each(function (Crawler $a) use (&$found) {
                if ($found) return;
                $href = $a->attr('href') ?? '';
                if ($href) $found = $href;
            });
        } catch (\Throwable) {}
        if ($found) return $found;

        // 3. Scan raw HTML for bases.athle.fr URL pattern
        if (preg_match('#(https?://bases\.athle\.fr/[^\s"\'<>]+seq=[^\s"\'<>]+)#i', $html, $m)) {
            return $m[1];
        }

        // 4. Scan for seq= parameter value anywhere (in scripts/data attributes)
        if (preg_match('/["\']?seq["\']?\s*[:=]\s*["\']?(\d{5,12})["\']?/i', $html, $m)) {
            $seq = $m[1];
            return self::BASES_URL . '?' . http_build_query(['base' => 'resultats', 'seq' => $seq]);
        }

        // 5. Scan for licence-like number near "licence" keyword
        if (preg_match('/licen[cs]e?\D{0,10}(\d{7,12})/i', $html, $m)) {
            return self::BASES_URL . '?' . http_build_query(['base' => 'resultats', 'seq' => $m[1]]);
        }

        // 6. If source URL itself contains a numeric ID, try it as seq
        if (preg_match('/[\/=](\d{7,12})(?:[\/&?]|$)/', $sourceUrl, $m)) {
            return self::BASES_URL . '?' . http_build_query(['base' => 'resultats', 'seq' => $m[1]]);
        }

        return null;
    }

    /** Parse name, birthDate, gender, discipline from any FFA page HTML */
    private function parseProfile(string $html): array
    {
        $crawler   = new Crawler($html);
        $firstName = null;
        $lastName  = null;

        // --- Name from <title> (most reliable on athle.fr) ---
        // Format: "Maksen ICHALLALEN | Profile | Fédération…"
        // Or:     "Résultats de ICHALLALEN Maksen"
        $candidates = [];
        try {
            $title = trim($crawler->filter('title')->first()->text());
            $part  = trim(explode('|', $title)[0]);
            $part  = preg_replace('/^résultats\s+de\s+/iu', '', $part);
            if (mb_strlen($part) > 2) $candidates[] = $part;
        } catch (\Throwable) {}

        foreach (['.athlete-name', '.athleteName', 'h1', 'h2', '.profile-name', '[class*="name"]'] as $sel) {
            try {
                $node = $crawler->filter($sel)->first();
                if ($node->count()) $candidates[] = trim(preg_replace('/\s+/', ' ', $node->text()));
            } catch (\Throwable) {}
        }

        foreach ($candidates as $c) {
            [$fn, $ln] = $this->splitName($c);
            if ($fn || $ln) { $firstName = $fn; $lastName = $ln; break; }
        }

        // --- Birth date & gender ---
        $birthDate = null;
        $gender    = null;
        if (preg_match('/né(e?)\s*(?:le\s+)?(\d{2}\/\d{2}\/\d{4})/iu', $html, $m)) {
            $dt = \DateTime::createFromFormat('d/m/Y', $m[2]);
            if ($dt) {
                $birthDate = $dt->format('Y-m-d');
                $gender    = (strtolower($m[1]) === 'e') ? 'F' : 'M';
            }
        } elseif (preg_match('/né(?:e)?\s+en\s+(19[4-9]\d|200\d|201\d)/iu', $html, $m)) {
            $birthDate = $m[1] . '-01-01';
        }

        // --- Dominant discipline ---
        $discipline = null;
        $counts     = [];
        try {
            $crawler->filter('td, span, div, p, li')->each(function (Crawler $node) use (&$counts) {
                $mapped = $this->mapDiscipline(trim($node->text()));
                if ($mapped) {
                    $counts[$mapped[0]] = ($counts[$mapped[0]] ?? 0) + 1;
                }
            });
        } catch (\Throwable) {}
        if ($counts) { arsort($counts); $discipline = array_key_first($counts); }

        return [
            'firstName'  => $firstName,
            'lastName'   => $lastName,
            'birthDate'  => $birthDate,
            'gender'     => $gender,
            'discipline' => $discipline,
            'error'      => (!$firstName && !$lastName)
                ? 'Nom introuvable sur cette page. Vérifiez l\'URL.'
                : null,
        ];
    }

    /**
     * Split "Firstname LASTNAME" or "LASTNAME Firstname" into [firstName, lastName].
     * ALL-CAPS words → last name, mixed-case words → first name.
     */
    private function splitName(string $text): array
    {
        $text  = preg_replace('/\s*[\|\-].*$/u', '', $text);
        $text  = preg_replace('/^résultats\s+de\s+/iu', '', $text);
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
            mb_convert_case(implode(' ', $upper),  MB_CASE_TITLE, 'UTF-8'),
        ];
    }

    /**
     * Parse all competition result rows from a bases.athle.fr HTML page.
     * Returns array of [discipline, unit, float value, \DateTime date].
     *
     * bases.athle.fr column order: Date | Epreuve | Performance | Vent | Tour | Place | Niveau | Points | Lieu
     */
    private function parseResults(string $html): array
    {
        $crawler = new Crawler($html);
        $results = [];

        try {
            $crawler->filter('table')->each(function (Crawler $table) use (&$results) {
                $colDate    = null;
                $colEpreuve = null;
                $colPerf    = null;

                $table->filter('tr')->each(function (Crawler $tr, int $rowIdx) use (
                    &$colDate, &$colEpreuve, &$colPerf, &$results
                ) {
                    $cells = $tr->filter('th, td');
                    if ($cells->count() < 3) return;

                    // Detect header row
                    if ($colDate === null && $colEpreuve === null) {
                        $texts = [];
                        $cells->each(fn(Crawler $c, int $i) => ($texts[$i] = mb_strtolower(trim($c->text()), 'UTF-8')));
                        $isHeader = false;
                        foreach ($texts as $i => $t) {
                            if (str_contains($t, 'date'))                               { $colDate    = $i; $isHeader = true; }
                            if (str_contains($t, 'preuve') || str_contains($t, 'iscipline')) { $colEpreuve = $i; $isHeader = true; }
                            if (str_contains($t, 'perf'))                               { $colPerf    = $i; $isHeader = true; }
                        }
                        if ($isHeader) return;
                    }

                    if ($colDate === null || $colEpreuve === null || $colPerf === null) return;

                    $texts = [];
                    $cells->each(fn(Crawler $td, int $i) => ($texts[$i] = trim($td->text())));

                    $mapped = $this->mapDiscipline($texts[$colEpreuve] ?? '');
                    if (!$mapped) return;

                    [$discipline, $unit] = $mapped;
                    $value = $this->parsePerf($texts[$colPerf] ?? '', $unit);
                    $date  = $this->parseDate($texts[$colDate] ?? '');

                    if ($value !== null && $date !== null) {
                        $results[] = [$discipline, $unit, $value, $date];
                    }
                });
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
     * Map a bases.athle.fr "Epreuve" value to [discipline, unit].
     * Strips indoor suffix "- Salle" before regex matching.
     */
    private function mapDiscipline(string $raw): ?array
    {
        $norm = preg_replace('/[-\s]*(salle|indoor|en\s+salle)\s*$/iu', '', $raw);
        $norm = trim(preg_replace('/\s+/', ' ', $norm));

        foreach (self::DISCIPLINE_PATTERNS as [$pattern, $discipline, $unit]) {
            if (preg_match($pattern, $norm)) {
                return [$discipline, $unit];
            }
        }

        return null;
    }

    /**
     * Parse a performance string into seconds (or metres/points).
     * Handles bases.athle.fr formats:
     *   7"34       → 7.34 s  (seconds"hundredths)
     *   1'23"45    → 83.45 s (minutes'seconds"hundredths)
     *   7,34 / 7.34
     */
    private function parsePerf(string $raw, string $unit): ?float
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (in_array(strtoupper($raw), ['-', 'DNS', 'DNF', 'DQ', 'NM', 'PM', 'AB', 'ABD', 'DISQ', 'NP'])) return null;

        if ($unit === 's') {
            // h'mm"ss  (rare long distances)
            if (preg_match("/^(\d+)[h'](\d+)['\"](\d+)[\".,](\d+)$/u", $raw, $m)) {
                return (int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3] + (int)$m[4] / 100;
            }
            // mm'ss"hh  or  mm'ss.hh
            if (preg_match("/^(\d+)['](\d+)[\".,](\d+)$/u", $raw, $m)) {
                return (int)$m[1] * 60 + (int)$m[2] + (int)$m[3] / 100;
            }
            // mm'ss
            if (preg_match("/^(\d+)['](\d+)$/u", $raw, $m)) {
                return (int)$m[1] * 60 + (float)$m[2];
            }
            // ss"hh  (bases.athle.fr standard: 7"34)
            if (preg_match('/^(\d+)"(\d+)$/', $raw, $m)) {
                return (int)$m[1] + (int)$m[2] / 100;
            }
            // h:mm:ss.hh
            $parts = explode(':', str_replace(',', '.', $raw));
            if (count($parts) === 3) return (int)$parts[0] * 3600 + (int)$parts[1] * 60 + (float)$parts[2];
            if (count($parts) === 2) return (int)$parts[0] * 60 + (float)$parts[1];
            // plain decimal
            $clean = str_replace(',', '.', $raw);
            return is_numeric($clean) ? (float)$clean : null;
        }

        $clean = str_replace([' ', ','], ['', '.'], $raw);
        return is_numeric($clean) ? (float)$clean : null;
    }

    /**
     * Parse a date string. Handles:
     *   d/m/Y, d-m-Y, Y-m-d
     *   "22 Nov. 2025", "20 Déc. 2025"  (French abbreviated months)
     */
    private function parseDate(string $raw): ?\DateTime
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt) { $dt->setTime(0, 0, 0); return $dt; }
        }

        // French abbreviated months: "22 Nov. 2025", "20 Déc. 2025"
        static $months = [
            'jan' => 1, 'fév' => 2, 'fev' => 2, 'mar' => 3,
            'avr' => 4, 'mai' => 5, 'jui' => 6, 'jul' => 7,
            'aoû' => 8, 'aou' => 8, 'sep' => 9, 'oct' => 10,
            'nov' => 11, 'déc' => 12, 'dec' => 12,
        ];

        if (preg_match('/(\d{1,2})\s+([A-Za-zÀ-ÿ]{3,5})\.?\s+(\d{4})/u', $raw, $m)) {
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

    private function fetch(string $url): ?string
    {
        try {
            $r = $this->httpClient->request('GET', $url, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9',
                    'Referer'         => 'https://www.athle.fr/',
                ],
            ]);
            if ($r->getStatusCode() !== 200) return null;
            return $r->getContent(false);
        } catch (\Throwable) {
            return null;
        }
    }
}
