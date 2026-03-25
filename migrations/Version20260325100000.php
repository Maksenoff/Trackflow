<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add upcoming training sessions';
    }

    public function up(Schema $schema): void
    {
        $sessions = [
            ['Fractionné court 10×200m',      '2026-03-28', 17, 75,  'Répétitions à 95% VMA. Récup 1min30 entre chaque.'],
            ['Endurance 12km',                 '2026-03-30', 14, 65,  'Allure confortable, FC < 75% max.'],
            ['Séance technique haies',         '2026-04-01', 15, 60,  'Passage de haies, rythme, fréquence.'],
            ['Fractionné seuil 5×1000m',       '2026-04-03', 13, 80,  'Au seuil lactique, récup 2min.'],
            ['PPG & renforcement',             '2026-04-05', 16, 55,  'Circuit musculaire : jambes, gainage, plyométrie.'],
            ['Sortie longue 16km',             '2026-04-07', 14, 90,  'Allure aérobie, terrain varié.'],
            ['Vitesse — départs et accélérations', '2026-04-09', 17, 50, 'Travail de départ bloc, accélérations 30-60m.'],
            ['VO2max 6×800m',                  '2026-04-12', 11, 70,  '110% VMA, récup 3min.'],
            ['Récupération active',            '2026-04-14', 19, 40,  'Footing léger + étirements.'],
            ['Fractionné long 3×2000m',        '2026-04-16', 13, 75,  'Allure semi-marathon, récup 3min.'],
            ['Séance sprint — 8×60m',          '2026-04-19', 17, 55,  'Effort maximal, récup complète 5min.'],
            ['Test terrain 3000m',             '2026-04-22', 20, 50,  'Évaluation de la forme du moment.'],
        ];

        foreach ($sessions as [$title, $date, $typeId, $duration, $desc]) {
            $this->addSql(
                'INSERT INTO session (title, date, training_type_id, duration_minutes, description) VALUES (?, ?, ?, ?, ?)',
                [$title, $date, $typeId, $duration, $desc]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM session WHERE date >= '2026-03-28' AND date <= '2026-04-22'");
    }
}
