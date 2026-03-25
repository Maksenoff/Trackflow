<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260324000011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__athlete AS SELECT id, first_name, last_name, birth_date, gender, discipline, notes, photo, created_at, last_synced_at, ffa_profile_url FROM athlete');
        $this->addSql('DROP TABLE athlete');
        $this->addSql('CREATE TABLE athlete (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, birth_date DATE DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, discipline CLOB NOT NULL, notes CLOB DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, last_synced_at DATETIME DEFAULT NULL, ffa_profile_url VARCHAR(500) DEFAULT NULL)');
        $this->addSql('INSERT INTO athlete (id, first_name, last_name, birth_date, gender, discipline, notes, photo, created_at, last_synced_at, ffa_profile_url) SELECT id, first_name, last_name, birth_date, gender, discipline, notes, photo, created_at, last_synced_at, ffa_profile_url FROM __temp__athlete');
        $this->addSql('DROP TABLE __temp__athlete');
        $this->addSql('ALTER TABLE user ADD COLUMN first_name VARCHAR(100) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE user ADD COLUMN last_name VARCHAR(100) NOT NULL DEFAULT \'\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__athlete AS SELECT id, first_name, last_name, birth_date, gender, discipline, notes, photo, ffa_profile_url, last_synced_at, created_at FROM athlete');
        $this->addSql('DROP TABLE athlete');
        $this->addSql('CREATE TABLE athlete (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, birth_date DATE DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, discipline VARCHAR(100) NOT NULL, notes CLOB DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, ffa_profile_url VARCHAR(500) DEFAULT NULL, last_synced_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO athlete (id, first_name, last_name, birth_date, gender, discipline, notes, photo, ffa_profile_url, last_synced_at, created_at) SELECT id, first_name, last_name, birth_date, gender, discipline, notes, photo, ffa_profile_url, last_synced_at, created_at FROM __temp__athlete');
        $this->addSql('DROP TABLE __temp__athlete');
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, roles, password, linked_athlete_id FROM "user"');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('CREATE TABLE "user" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, linked_athlete_id INTEGER DEFAULT NULL, CONSTRAINT FK_8D93D64969AF10CC FOREIGN KEY (linked_athlete_id) REFERENCES athlete (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "user" (id, email, roles, password, linked_athlete_id) SELECT id, email, roles, password, linked_athlete_id FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64969AF10CC ON "user" (linked_athlete_id)');
    }
}
