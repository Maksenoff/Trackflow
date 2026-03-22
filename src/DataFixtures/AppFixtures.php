<?php

namespace App\DataFixtures;

use App\Entity\Athlete;
use App\Entity\AthleteSession;
use App\Entity\Goal;
use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $perfDates = ['-5 months', '-4 months', '-3 months', '-2 months', '-1 month', '-2 weeks'];

        // ── Training types ──
        $typesData = [
            ['VO2max',               '#ef4444'],
            ['Lactique',             '#f97316'],
            ['Seuil aérobie',        '#eab308'],
            ['Endurance fondamentale','#3b82f6'],
            ['Technique',            '#8b5cf6'],
            ['Force / PPG',          '#ec4899'],
            ['Vitesse / Sprint',     '#f59e0b'],
            ['Compétition',          '#10b981'],
            ['Récupération active',  '#06b6d4'],
            ['Test / Évaluation',    '#6366f1'],
        ];

        $types = [];
        foreach ($typesData as [$name, $color]) {
            $tt = new TrainingType();
            $tt->setName($name)->setColor($color);
            $manager->persist($tt);
            $types[$name] = $tt;
        }

        // ── Athletes ──
        $thomas = new Athlete();
        $thomas->setFirstName('Thomas')->setLastName('Dupont')
            ->setBirthDate(new \DateTime('2001-06-15'))->setGender('M')
            ->setDiscipline('100m')
            ->setNotes('Excellent potentiel sur courte distance. Travail sur la sortie de starting-blocks en cours.');
        $manager->persist($thomas);

        $marie = new Athlete();
        $marie->setFirstName('Marie')->setLastName('Laurent')
              ->setBirthDate(new \DateTime('2003-03-22'))->setGender('F')
              ->setDiscipline('longueur')->setNotes('Progrès constants. Phase de bond à améliorer.');
        $manager->persist($marie);

        $kevin = new Athlete();
        $kevin->setFirstName('Kevin')->setLastName('Martin')
              ->setBirthDate(new \DateTime('1999-11-08'))->setGender('M')
              ->setDiscipline('poids')->setNotes('Athlète confirmé. Renforcement musculaire en cours.');
        $manager->persist($kevin);

        // ── Sessions ──
        $sessionsData = [
            ['-10 days', 'Fractionné court 10×100m',        'VO2max',               75, "Échauffement 15min → 10×100m à 95% VMA récup 1min → retour au calme 10min"],
            ['-8 days',  'Séance technique départ',          'Technique',            60, null],
            ['-5 days',  'Circuit PPG membres inférieurs',   'Force / PPG',          90, "Squats, fentes, gainage. 4 séries."],
            ['-3 days',  'Endurance longue 45min',           'Endurance fondamentale',45, null],
            ['-1 day',   'Lactique 5×300m',                  'Lactique',             80, "Échauffement 15min → 5×300m allure lactique récup 3min → retour au calme"],
            ['today',    'Récupération active',               'Récupération active',  40, null],
            ['+3 days',  'Test 60m plat',                    'Test / Évaluation',    50, null],
            ['+7 days',  'Fractionné seuil 6×400m',          'Seuil aérobie',        90, null],
            ['+14 days', 'Compétition régionale',             'Compétition',         120, null],
        ];

        $sessions = [];
        foreach ($sessionsData as [$dateStr, $title, $typeName, $dur, $desc]) {
            $s = new Session();
            $d = $dateStr === 'today' ? new \DateTime() : new \DateTime($dateStr);
            $s->setTitle($title)->setDate($d)->setDurationMinutes($dur);
            if (isset($types[$typeName])) $s->setTrainingType($types[$typeName]);
            if ($desc) $s->setDescription($desc);
            $manager->persist($s);
            $sessions[$title] = $s;
        }

        // ── AthleteSession feedbacks ──
        $feedbacks = [
            [$thomas, 'Fractionné court 10×100m',       8, 'Très intense, bonnes sensations. Jambes lourdes à la fin.'],
            [$thomas, 'Séance technique départ',         5, 'Travail propre, réactions au départ en progrès.'],
            [$thomas, 'Circuit PPG membres inférieurs',  7, 'Difficile mais motivant. Cuisses bien sollicitées.'],
            [$marie,  'Fractionné court 10×100m',        6, 'Bonne séance globalement.'],
            [$marie,  'Séance technique départ',         4, 'Facile, bien récupérée.'],
            [$kevin,  'Circuit PPG membres inférieurs',  9, 'Très dur, limite sur les dernières séries. Bon signe !'],
        ];

        foreach ($feedbacks as [$athlete, $title, $diff, $comment]) {
            if (!isset($sessions[$title])) continue;
            $asl = new AthleteSession();
            $asl->setAthlete($athlete)->setSession($sessions[$title])
                ->setDifficulty($diff)->setComment($comment);
            $manager->persist($asl);
        }

        // ── Performances ──
        // Thomas – 100m (time, lower is better)
        foreach ([10.95,10.88,10.82,10.75,10.71,10.68] as $i => $t) {
            $p = new Performance();
            $p->setAthlete($thomas)->setDiscipline('100m')->setValue((string)$t)->setUnit('s')
              ->setRecordedAt(new \DateTime($perfDates[$i]))
              ->setIsPersonalBest($i === 5)->setIsCompetition($i === 2 || $i === 5);
            $manager->persist($p);
        }
        // Thomas – 200m (time, lower is better)
        foreach ([22.10,21.95,21.80,21.70,21.58,21.45] as $i => $t) {
            $p = new Performance();
            $p->setAthlete($thomas)->setDiscipline('200m')->setValue((string)$t)->setUnit('s')
              ->setRecordedAt(new \DateTime($perfDates[$i]))
              ->setIsPersonalBest($i === 5)->setIsCompetition($i === 3);
            $manager->persist($p);
        }
        // Marie – longueur (distance, higher is better)
        foreach ([5.45,5.52,5.60,5.58,5.71,5.80] as $i => $d) {
            $p = new Performance();
            $p->setAthlete($marie)->setDiscipline('longueur')->setValue((string)$d)->setUnit('m')
              ->setRecordedAt(new \DateTime($perfDates[$i]))
              ->setIsPersonalBest($i === 5)->setIsCompetition($i === 3);
            $manager->persist($p);
        }
        // Marie – triple saut (distance, higher is better)
        foreach ([11.80,12.05,12.25,12.40,12.60,12.85] as $i => $d) {
            $p = new Performance();
            $p->setAthlete($marie)->setDiscipline('triple saut')->setValue((string)$d)->setUnit('m')
              ->setRecordedAt(new \DateTime($perfDates[$i]))
              ->setIsPersonalBest($i === 5)->setIsCompetition($i === 2);
            $manager->persist($p);
        }
        // Kevin – poids (distance, higher is better)
        foreach ([14.20,14.55,14.80,15.10,15.35,15.62] as $i => $d) {
            $p = new Performance();
            $p->setAthlete($kevin)->setDiscipline('poids')->setValue((string)$d)->setUnit('m')
              ->setRecordedAt(new \DateTime($perfDates[$i]))
              ->setIsPersonalBest($i === 5)->setIsCompetition($i === 1 || $i === 4);
            $manager->persist($p);
        }
        // Kevin – disque (distance, higher is better)
        foreach ([38.50,39.20,40.10,41.30,42.80,44.15] as $i => $d) {
            $p = new Performance();
            $p->setAthlete($kevin)->setDiscipline('disque')->setValue((string)$d)->setUnit('m')
              ->setRecordedAt(new \DateTime($perfDates[$i]))
              ->setIsPersonalBest($i === 5)->setIsCompetition($i === 2 || $i === 4);
            $manager->persist($p);
        }

        // ── Goals ──
        $g1 = new Goal();
        $g1->setAthlete($thomas)->setTitle('Passer sous les 10.60s au 100m')
           ->setDiscipline('100m')->setTargetValue('10.60')->setUnit('s')
           ->setDeadline(new \DateTime('+3 months'))->setStatus('in_progress');
        $manager->persist($g1);

        $g2 = new Goal();
        $g2->setAthlete($thomas)->setTitle('Qualification championnats régionaux')
           ->setStatus('achieved')->setNotes('Obtenue lors du meeting de Lyon.');
        $manager->persist($g2);

        $g3 = new Goal();
        $g3->setAthlete($marie)->setTitle('Atteindre 6.00m en longueur')
           ->setDiscipline('longueur')->setTargetValue('6.00')->setUnit('m')
           ->setDeadline(new \DateTime('+6 months'))->setStatus('in_progress');
        $manager->persist($g3);

        $manager->flush();
    }
}
