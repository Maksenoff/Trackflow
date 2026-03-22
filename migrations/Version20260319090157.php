<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319090157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE athlete (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, birth_date DATE DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, discipline VARCHAR(100) NOT NULL, notes CLOB DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE TABLE athlete_session (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, comment CLOB DEFAULT NULL, difficulty INTEGER DEFAULT NULL, logged_at DATETIME NOT NULL, athlete_id INTEGER NOT NULL, session_id INTEGER NOT NULL, CONSTRAINT FK_AF86B9BBFE6BCB8B FOREIGN KEY (athlete_id) REFERENCES athlete (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AF86B9BB613FECDF FOREIGN KEY (session_id) REFERENCES session (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_AF86B9BBFE6BCB8B ON athlete_session (athlete_id)');
        $this->addSql('CREATE INDEX IDX_AF86B9BB613FECDF ON athlete_session (session_id)');
        $this->addSql('CREATE UNIQUE INDEX athlete_session_unique ON athlete_session (athlete_id, session_id)');
        $this->addSql('CREATE TABLE goal (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, discipline VARCHAR(50) DEFAULT NULL, target_value NUMERIC(10, 3) DEFAULT NULL, unit VARCHAR(20) DEFAULT NULL, deadline DATE DEFAULT NULL, status VARCHAR(20) NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, athlete_id INTEGER NOT NULL, CONSTRAINT FK_FCDCEB2EFE6BCB8B FOREIGN KEY (athlete_id) REFERENCES athlete (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_FCDCEB2EFE6BCB8B ON goal (athlete_id)');
        $this->addSql('CREATE TABLE performance (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, discipline VARCHAR(50) NOT NULL, value NUMERIC(10, 3) NOT NULL, unit VARCHAR(20) NOT NULL, notes CLOB DEFAULT NULL, recorded_at DATE NOT NULL, is_personal_best BOOLEAN DEFAULT NULL, is_competition BOOLEAN DEFAULT NULL, athlete_id INTEGER NOT NULL, session_id INTEGER DEFAULT NULL, CONSTRAINT FK_82D79681FE6BCB8B FOREIGN KEY (athlete_id) REFERENCES athlete (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_82D79681613FECDF FOREIGN KEY (session_id) REFERENCES session (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_82D79681FE6BCB8B ON performance (athlete_id)');
        $this->addSql('CREATE INDEX IDX_82D79681613FECDF ON performance (session_id)');
        $this->addSql('CREATE TABLE session (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(150) NOT NULL, date DATE NOT NULL, description CLOB DEFAULT NULL, duration_minutes INTEGER DEFAULT NULL, training_type_id INTEGER DEFAULT NULL, CONSTRAINT FK_D044D5D418721C9D FOREIGN KEY (training_type_id) REFERENCES training_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D044D5D418721C9D ON session (training_type_id)');
        $this->addSql('CREATE TABLE training_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, color VARCHAR(7) NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE athlete');
        $this->addSql('DROP TABLE athlete_session');
        $this->addSql('DROP TABLE goal');
        $this->addSql('DROP TABLE performance');
        $this->addSql('DROP TABLE session');
        $this->addSql('DROP TABLE training_type');
    }
}
