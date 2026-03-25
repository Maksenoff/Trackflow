<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322200610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE performance ADD COLUMN is_indoor BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE performance ADD COLUMN venue VARCHAR(150) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__performance AS SELECT id, discipline, value, unit, notes, recorded_at, is_personal_best, is_competition, athlete_id, session_id FROM performance');
        $this->addSql('DROP TABLE performance');
        $this->addSql('CREATE TABLE performance (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, discipline VARCHAR(50) NOT NULL, value NUMERIC(10, 3) NOT NULL, unit VARCHAR(20) NOT NULL, notes CLOB DEFAULT NULL, recorded_at DATE NOT NULL, is_personal_best BOOLEAN DEFAULT NULL, is_competition BOOLEAN DEFAULT NULL, athlete_id INTEGER NOT NULL, session_id INTEGER DEFAULT NULL, CONSTRAINT FK_82D79681FE6BCB8B FOREIGN KEY (athlete_id) REFERENCES athlete (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_82D79681613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO performance (id, discipline, value, unit, notes, recorded_at, is_personal_best, is_competition, athlete_id, session_id) SELECT id, discipline, value, unit, notes, recorded_at, is_personal_best, is_competition, athlete_id, session_id FROM __temp__performance');
        $this->addSql('DROP TABLE __temp__performance');
        $this->addSql('CREATE INDEX IDX_82D79681FE6BCB8B ON performance (athlete_id)');
        $this->addSql('CREATE INDEX IDX_82D79681613FECDF ON performance (session_id)');
    }
}
