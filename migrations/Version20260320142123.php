<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320142123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE athlete ADD COLUMN licence VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE athlete ADD COLUMN last_synced_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__athlete AS SELECT id, first_name, last_name, birth_date, gender, discipline, notes, photo, created_at FROM athlete');
        $this->addSql('DROP TABLE athlete');
        $this->addSql('CREATE TABLE athlete (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, birth_date DATE DEFAULT NULL, gender VARCHAR(20) DEFAULT NULL, discipline VARCHAR(100) NOT NULL, notes CLOB DEFAULT NULL, photo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO athlete (id, first_name, last_name, birth_date, gender, discipline, notes, photo, created_at) SELECT id, first_name, last_name, birth_date, gender, discipline, notes, photo, created_at FROM __temp__athlete');
        $this->addSql('DROP TABLE __temp__athlete');
    }
}
