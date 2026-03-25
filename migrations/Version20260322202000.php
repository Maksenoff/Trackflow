<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260322202000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add level_points column to performance table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE performance ADD COLUMN level_points INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__performance AS SELECT id, discipline, value, unit, notes, recorded_at, is_personal_best, is_competition, is_indoor, venue, level, athlete_id, session_id FROM performance');
        $this->addSql('DROP TABLE performance');
        $this->addSql('CREATE TABLE performance (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, discipline VARCHAR(50) NOT NULL, value NUMERIC(10,3) NOT NULL, unit VARCHAR(20) NOT NULL, notes CLOB DEFAULT NULL, recorded_at DATE NOT NULL, is_personal_best BOOLEAN DEFAULT NULL, is_competition BOOLEAN DEFAULT NULL, is_indoor BOOLEAN DEFAULT NULL, venue VARCHAR(150) DEFAULT NULL, level VARCHAR(15) DEFAULT NULL, athlete_id INTEGER NOT NULL, session_id INTEGER DEFAULT NULL)');
        $this->addSql('INSERT INTO performance SELECT id, discipline, value, unit, notes, recorded_at, is_personal_best, is_competition, is_indoor, venue, level, athlete_id, session_id FROM __temp__performance');
        $this->addSql('DROP TABLE __temp__performance');
    }
}
